/**
 * admin/js/call-notification.js
 *
 * Polls for incoming calls, shows caller details to all logged-in admins,
 * and handles WebRTC answer + ICE exchange for the admin who accepts.
 *
 * Injected via admin/includes/footer.php (only when BASE_URL is defined).
 */
(function () {
    "use strict";

    if (typeof BASE_URL === "undefined") return;

    // ── State ─────────────────────────────────────────────────────────────
    var activeCallId   = null;   // call_id being answered by THIS admin tab
    var pc             = null;   // RTCPeerConnection for active call
    var localStream    = null;
    var isMuted        = false;
    var timerInterval  = null;
    var timerSeconds   = 0;
    var iceFlushTimer  = null;
    var iceQueue       = [];
    var knownCallIds   = {};     // { callId: true } – tracks calls shown already
    var pollInterval   = null;
    var lateIceInterval= null;
    var callerIceCount = 0;      // how many caller ICE candidates we've already applied

    var STUN_CONFIG = {
        iceServers: [
            { urls: "stun:stun.l.google.com:19302" },
            { urls: "stun:stun1.l.google.com:19302" }
        ]
    };

    // ── Bootstrap modal instance ──────────────────────────────────────────
    var modalEl    = null;
    var modalInst  = null;

    // ── Notification audio ────────────────────────────────────────────────
    // Short beep synthesised with the Web Audio API (no external file needed)
    function playRingBeep() {
        try {
            var ctx   = new (window.AudioContext || window.webkitAudioContext)();
            var osc   = ctx.createOscillator();
            var gain  = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type      = "sine";
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.4, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.8);
        } catch (e) { /* AudioContext unavailable */ }
    }

    // ── Timer helpers ─────────────────────────────────────────────────────
    function startTimer() {
        timerSeconds = 0;
        timerInterval = setInterval(function () {
            timerSeconds++;
            var m = Math.floor(timerSeconds / 60);
            var s = timerSeconds % 60;
            var el = document.getElementById("callAdminTimer");
            if (el) el.textContent = m + ":" + (s < 10 ? "0" : "") + s;
        }, 1000);
    }
    function stopTimer() {
        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    }

    // ── ICE batching ──────────────────────────────────────────────────────
    function scheduleIceSend(candidate) {
        iceQueue.push(candidate);
        if (!iceFlushTimer) iceFlushTimer = setTimeout(flushIce, 500);
    }
    function flushIce() {
        iceFlushTimer = null;
        if (!activeCallId || iceQueue.length === 0) return;
        var batch = iceQueue.splice(0);
        batch.forEach(function (c) {
            var fd = new FormData();
            fd.append("call_id",   activeCallId);
            fd.append("role",      "admin");
            fd.append("candidate", JSON.stringify(c));
            fetch(BASE_URL + "/api/call/signal.php", { method: "POST", body: fd }).catch(function(){});
        });
    }

    // ── Build caller-details HTML ─────────────────────────────────────────
    function callerBadgeHTML(call) {
        var typeClass = call.account_type === "premium" ? "bg-warning text-dark" : "bg-secondary";
        var typeLabel = call.account_type === "premium" ? "⭐ Premium" : "Free";
        var html = '<div class="d-flex align-items-center gap-3 mb-3">';
        html    += '  <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0"'
                 + '       style="width:52px;height:52px;font-size:1.4rem;">'
                 + '    <i class="fas fa-user"></i></div>';
        html    += '  <div>';
        html    += '    <div class="fw-bold fs-5">' + escHtml(call.caller_name) + '</div>';
        html    += '    <div class="text-muted small"><i class="fas fa-phone me-1"></i>' + escHtml(call.caller_phone) + '</div>';
        html    += '  </div>';
        html    += '</div>';
        html    += '<div class="d-flex flex-wrap gap-2 mb-3">';
        html    += '  <span class="badge ' + typeClass + ' px-3 py-2">' + typeLabel + ' User</span>';
        if (call.last_booking_number) {
            html += '  <span class="badge bg-info text-dark px-3 py-2">'
                 +  '<i class="fas fa-receipt me-1"></i>Booking #' + escHtml(call.last_booking_number) + '</span>';
        }
        if (call.last_package_name) {
            html += '  <span class="badge bg-light text-dark border px-3 py-2">'
                 +  '<i class="fas fa-box me-1"></i>' + escHtml(call.last_package_name) + '</span>';
        }
        html    += '</div>';

        // Waiting time
        if (call.waiting_seconds > 0) {
            var wm = Math.floor(call.waiting_seconds / 60);
            var ws = call.waiting_seconds % 60;
            var wLabel = wm > 0 ? wm + "m " + ws + "s" : ws + "s";
            html += '<div class="text-muted small mb-3"><i class="fas fa-clock me-1"></i>Waiting: ' + wLabel + '</div>';
        }
        return html;
    }

    function escHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    // ── Render pending calls in the queue panel ───────────────────────────
    function renderCallQueue(calls) {
        var list = document.getElementById("callQueueList");
        if (!list) return;
        list.innerHTML = "";
        calls.forEach(function (call) {
            if (call.status === "active" && call.accepted_by !== null && call.call_id !== activeCallId) {
                // Being handled by another admin – show info-only row
                var row = document.createElement("div");
                row.className = "list-group-item list-group-item-info py-2 px-3 small";
                row.innerHTML = '<i class="fas fa-phone-volume me-2"></i>'
                    + '<strong>' + escHtml(call.caller_name) + '</strong>'
                    + ' – in call with ' + escHtml(call.accepted_by_name || "another admin");
                list.appendChild(row);
                return;
            }
            if (call.call_id === activeCallId) return; // shown in main modal

            var typeClass = call.account_type === "premium" ? "bg-warning text-dark" : "bg-secondary";
            var typeLabel = call.account_type === "premium" ? "⭐ Premium" : "Free";

            var item = document.createElement("div");
            item.className = "list-group-item list-group-item-action py-2 px-3";
            item.dataset.callId = call.call_id;
            item.innerHTML
                = '<div class="d-flex justify-content-between align-items-start">'
                + '  <div>'
                + '    <strong>' + escHtml(call.caller_name) + '</strong>'
                + '    <span class="badge ' + typeClass + ' ms-2 py-1">' + typeLabel + '</span>'
                + '    <div class="text-muted small"><i class="fas fa-phone me-1"></i>' + escHtml(call.caller_phone) + '</div>'
                + (call.last_booking_number ? '<div class="text-muted small">'
                    + '<i class="fas fa-receipt me-1"></i>Booking #' + escHtml(call.last_booking_number) + '</div>' : '')
                + '  </div>'
                + '  <button class="btn btn-success btn-sm ms-2 answer-queue-btn" data-call-id="' + call.call_id + '">'
                + '    <i class="fas fa-phone"></i></button>'
                + '</div>';
            list.appendChild(item);
        });

        // Bind answer buttons in queue
        list.querySelectorAll(".answer-queue-btn").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var cid = parseInt(this.dataset.callId, 10);
                openAnswerModal(cid);
            });
        });
    }

    // ── Helper: should we pop the incoming-call modal now? ──────────────────
    // Returns true when this admin tab is free (not on an active call and
    // modal is not already open).
    function canShowIncomingModal() {
        if (activeCallId) return false;                              // already on a call
        if (!modalEl)     return false;                              // modal not in DOM
        if (modalEl.classList.contains("show")) return false;        // modal already open
        return true;
    }

    // ── Open the incoming-call modal for a specific call ──────────────────
    function openAnswerModal(callId) {
        // Fetch full call details
        fetch(BASE_URL + "/api/call/poll.php?call_id=" + callId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                // Find call in last known list
                var call = lastKnownCalls.find(function (c) { return c.call_id === callId; });
                if (!call) return;
                showIncomingModal(call, data);
            }).catch(function(){});
    }

    var lastKnownCalls = [];

    // ── Show incoming call modal ──────────────────────────────────────────
    function showIncomingModal(call, signalData) {
        var detailsEl = document.getElementById("callerDetailsHTML");
        if (detailsEl) detailsEl.innerHTML = callerBadgeHTML(call);

        // Buttons
        var acceptBtn  = document.getElementById("acceptCallBtn");
        var declineBtn = document.getElementById("declineCallBtn");
        var endBtn     = document.getElementById("endCallAdminBtn");
        if (acceptBtn)  acceptBtn.dataset.callId = call.call_id;
        if (declineBtn) declineBtn.dataset.callId = call.call_id;

        // Show answer controls (not end-call controls)
        setCallModalState("ringing");

        if (!modalInst && modalEl) {
            modalInst = new bootstrap.Modal(modalEl, { backdrop: "static", keyboard: false });
        }
        if (modalInst) modalInst.show();

        playRingBeep();

        // Store offer SDP for later use when accepting
        modalEl._pendingOfferSdp   = signalData.offer_sdp;
        modalEl._pendingCallerIce  = signalData.caller_ice || [];
        modalEl._pendingCallId     = call.call_id;
    }

    // ── Modal state toggling ─────────────────────────────────────────────
    function setCallModalState(state) {
        var ringingEl = document.getElementById("callModalRinging");
        var activeEl  = document.getElementById("callModalActive");
        var endedEl   = document.getElementById("callModalEnded");
        if (ringingEl) ringingEl.classList.toggle("d-none", state !== "ringing");
        if (activeEl)  activeEl.classList.toggle("d-none",  state !== "active");
        if (endedEl)   endedEl.classList.toggle("d-none",   state !== "ended");
    }

    // ── Accept call ───────────────────────────────────────────────────────
    function acceptCall(callId, offerSdpJson, callerIceCandidates) {
        if (!offerSdpJson) {
            // Try to fetch if not already available
            fetch(BASE_URL + "/api/call/poll.php?call_id=" + callId)
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success && d.offer_sdp) {
                        acceptCall(callId, d.offer_sdp, d.caller_ice || []);
                    }
                }).catch(function(){});
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true, video: false })
            .then(function (stream) {
                localStream   = stream;
                activeCallId  = callId;
                callerIceCount = 0;

                // Setup peer connection
                pc = new RTCPeerConnection(STUN_CONFIG);

                pc.onicecandidate = function (evt) {
                    if (evt.candidate) scheduleIceSend(evt.candidate.toJSON ? evt.candidate.toJSON() : evt.candidate);
                };

                pc.ontrack = function (evt) {
                    var audio = document.getElementById("callAdminRemoteAudio");
                    if (!audio) return;
                    if (evt.streams && evt.streams[0]) {
                        audio.srcObject = evt.streams[0];
                    } else {
                        var ms = new MediaStream(); ms.addTrack(evt.track); audio.srcObject = ms;
                    }
                };

                pc.onconnectionstatechange = function () {
                    if (pc.connectionState === "connected") {
                        setCallModalState("active");
                        startTimer();
                    }
                    if (["disconnected","failed","closed"].indexOf(pc.connectionState) !== -1) {
                        endActiveCall(false);
                    }
                };

                // Add local tracks
                stream.getTracks().forEach(function (t) { pc.addTrack(t, stream); });

                // Set remote description (offer from caller)
                var offerSdp;
                try { offerSdp = JSON.parse(offerSdpJson); } catch(e) { return; }

                pc.setRemoteDescription(new RTCSessionDescription(offerSdp))
                    .then(function () {
                        // Add caller ICE candidates
                        callerIceCandidates.forEach(function (c) {
                            pc.addIceCandidate(new RTCIceCandidate(c)).catch(function(){});
                        });
                        callerIceCount = callerIceCandidates.length;
                        return pc.createAnswer();
                    })
                    .then(function (answer) { return pc.setLocalDescription(answer); })
                    .then(function () {
                        // POST answer to server
                        var fd = new FormData();
                        fd.append("call_id",    callId);
                        fd.append("answer_sdp", JSON.stringify(pc.localDescription));
                        return fetch(BASE_URL + "/api/call/accept.php", { method: "POST", body: fd });
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.success) {
                            alert(data.message || "Could not accept call.");
                            endActiveCall(false);
                            return;
                        }
                        // Start slow polling to pick up late caller ICE
                        lateIceInterval = setInterval(pollLateCallerIce, 3000);
                    })
                    .catch(function (err) {
                        console.error("acceptCall error:", err);
                        endActiveCall(false);
                    });
            })
            .catch(function () {
                alert("Microphone access is required to answer calls. Please allow microphone permission.");
            });
    }

    function pollLateCallerIce() {
        if (!activeCallId || !pc) return;
        fetch(BASE_URL + "/api/call/poll.php?call_id=" + activeCallId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                if (data.status === "ended") { endActiveCall(false); return; }
                var candidates = data.caller_ice || [];
                if (candidates.length > callerIceCount) {
                    candidates.slice(callerIceCount).forEach(function (c) {
                        pc.addIceCandidate(new RTCIceCandidate(c)).catch(function(){});
                    });
                    callerIceCount = candidates.length;
                }
            }).catch(function(){});
    }

    // ── End active call (admin side) ──────────────────────────────────────
    function endActiveCall(notifyServer) {
        if (notifyServer !== false && activeCallId) {
            var fd = new FormData();
            fd.append("call_id", activeCallId);
            fd.append("reason",  "ended");
            fetch(BASE_URL + "/api/call/decline.php", { method: "POST", body: fd }).catch(function(){});
        }
        if (lateIceInterval) { clearInterval(lateIceInterval); lateIceInterval = null; }
        stopTimer();
        if (localStream) { localStream.getTracks().forEach(function (t) { t.stop(); }); localStream = null; }
        if (pc) { pc.close(); pc = null; }
        activeCallId = null; isMuted = false;
        setCallModalState("ended");
        updateBadge();
    }

    // ── Mute toggle ───────────────────────────────────────────────────────
    function toggleMute() {
        if (!localStream) return;
        isMuted = !isMuted;
        localStream.getAudioTracks().forEach(function (t) { t.enabled = !isMuted; });
        var btn  = document.getElementById("muteCallAdminBtn");
        var icon = document.getElementById("muteCallAdminIcon");
        var text = document.getElementById("muteCallAdminText");
        if (btn)  btn.className  = isMuted ? "btn btn-warning me-2" : "btn btn-outline-secondary me-2";
        if (icon) icon.className = isMuted ? "fas fa-microphone-slash" : "fas fa-microphone";
        if (text) text.textContent = isMuted ? "Unmute" : "Mute";
    }

    // ── Badge update ──────────────────────────────────────────────────────
    function updateBadge(count) {
        var badge = document.getElementById("callNotifBadge");
        if (!badge) return;
        count = count || 0;
        badge.textContent = count;
        badge.classList.toggle("d-none", count === 0);
    }

    // ── Main polling loop ─────────────────────────────────────────────────
    function pollAdminCalls() {
        fetch(BASE_URL + "/api/call/poll.php")
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                var calls   = data.calls || [];
                lastKnownCalls = calls;

                var pending = calls.filter(function (c) { return c.status === "pending"; });
                updateBadge(pending.length);
                renderCallQueue(calls);

                // Show incoming modal for new pending calls (not already known)
                pending.forEach(function (call) {
                    if (!knownCallIds[call.call_id]) {
                        knownCallIds[call.call_id] = true;
                        if (canShowIncomingModal()) {
                            openAnswerModal(call.call_id);
                        } else {
                            playRingBeep(); // beep so admin knows another call arrived
                        }
                    }
                });

                // Clean up knownCallIds for finished calls
                calls.forEach(function (c) {
                    if (c.status !== "pending" && c.status !== "active") {
                        delete knownCallIds[c.call_id];
                    }
                });
            })
            .catch(function () {});
    }

    // ── DOM ready ─────────────────────────────────────────────────────────
    document.addEventListener("DOMContentLoaded", function () {
        modalEl = document.getElementById("incomingCallModal");
        if (!modalEl) return; // header not injected

        // Accept button
        var acceptBtn = document.getElementById("acceptCallBtn");
        if (acceptBtn) {
            acceptBtn.addEventListener("click", function () {
                var cid = parseInt(this.dataset.callId, 10);
                setCallModalState("active");
                acceptCall(
                    cid,
                    modalEl._pendingOfferSdp   || null,
                    modalEl._pendingCallerIce  || []
                );
            });
        }

        // Decline button
        var declineBtn = document.getElementById("declineCallBtn");
        if (declineBtn) {
            declineBtn.addEventListener("click", function () {
                var cid = parseInt(this.dataset.callId, 10);
                if (cid) {
                    var fd = new FormData();
                    fd.append("call_id", cid);
                    fd.append("reason",  "declined");
                    fetch(BASE_URL + "/api/call/decline.php", { method: "POST", body: fd }).catch(function(){});
                }
                if (modalInst) modalInst.hide();
            });
        }

        // End call button (admin)
        var endBtn = document.getElementById("endCallAdminBtn");
        if (endBtn) endBtn.addEventListener("click", function () {
            endActiveCall(true);
            if (modalInst) modalInst.hide();
        });

        // Mute button (admin)
        var muteBtn = document.getElementById("muteCallAdminBtn");
        if (muteBtn) muteBtn.addEventListener("click", toggleMute);

        // Close modal triggers cleanup
        modalEl.addEventListener("hidden.bs.modal", function () {
            if (!activeCallId) { setCallModalState("ringing"); }
        });

        // Notification bell click – open queue panel
        var bellBtn = document.getElementById("callNotifBell");
        if (bellBtn) {
            bellBtn.addEventListener("click", function () {
                var panel = document.getElementById("callQueuePanel");
                if (panel) panel.classList.toggle("d-none");
            });
        }

        // Start polling every 3 seconds
        pollInterval = setInterval(pollAdminCalls, 3000);
        pollAdminCalls(); // immediate first poll
    });

})();

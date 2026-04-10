<?php
$page_title       = 'Call Admin';
$page_description = 'Talk directly with our admin team. We\'re available to answer your questions about bookings and packages.';
require_once __DIR__ . '/includes/header.php';

$office_phone = getSetting('office_phone', '');
?>

<!-- Page Hero -->
<div class="page-hero-bar bg-success text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/" class="text-white">Home</a></li>
                <li class="breadcrumb-item active text-white-50">Call Admin</li>
            </ol>
        </nav>
        <h1 class="h3 mt-2 mb-0"><i class="fas fa-headset me-2"></i>Talk to Our Team</h1>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <!-- Step 1: Enter details & initiate call -->
            <div id="callSetupCard" class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-phone-alt me-2"></i>Start a Call with Admin</h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted mb-4">
                        Enter your registered phone number so the admin can see your booking details.
                    </p>

                    <div id="callSetupForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Your Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" id="callerPhone" class="form-control form-control-lg"
                                   placeholder="e.g. 9800000000" maxlength="15" required>
                            <div class="form-text">Use your registered number so we can identify your account.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Your Name</label>
                            <input type="text" id="callerName" class="form-control"
                                   placeholder="Full name (optional)" maxlength="100">
                        </div>

                        <div id="micWarning" class="alert alert-warning d-none">
                            <i class="fas fa-microphone-slash me-2"></i>
                            Microphone access is needed for the call. Please allow microphone permission when prompted.
                        </div>

                        <button type="button" id="startCallBtn" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-phone me-2"></i>Call Admin Now
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Calling / in-call UI -->
            <div id="callActiveCard" class="card shadow-sm d-none">
                <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="fas fa-phone-alt me-2"></i>Call in Progress</h5>
                    <span id="callTimer" class="badge bg-light text-dark">0:00</span>
                </div>
                <div class="card-body p-4 text-center">
                    <!-- Status display -->
                    <div id="callStatusIcon" class="mb-3">
                        <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center"
                             style="width:80px;height:80px;font-size:2rem;">
                            <i class="fas fa-phone fa-spin" id="callIcon"></i>
                        </div>
                    </div>
                    <h5 id="callStatusText" class="mb-1">Connecting…</h5>
                    <p id="callSubText" class="text-muted small">Please wait while we connect you to an admin.</p>

                    <!-- Queue position -->
                    <div id="queueInfo" class="alert alert-info d-none mt-3">
                        <i class="fas fa-clock me-2"></i>
                        <span id="queueText">You are in the queue. An admin will answer shortly.</span>
                    </div>

                    <!-- Hidden audio element for remote stream -->
                    <audio id="remoteAudio" autoplay playsinline style="display:none;"></audio>

                    <!-- Controls shown once call is active -->
                    <div id="callControls" class="d-none mt-4">
                        <button type="button" id="muteBtn" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-microphone" id="muteIcon"></i>
                            <span id="muteText">Mute</span>
                        </button>
                        <button type="button" id="endCallBtn" class="btn btn-danger">
                            <i class="fas fa-phone-slash me-2"></i>End Call
                        </button>
                    </div>

                    <!-- Cancel while ringing -->
                    <div id="cancelControls" class="mt-4">
                        <button type="button" id="cancelCallBtn" class="btn btn-outline-danger">
                            <i class="fas fa-times me-2"></i>Cancel Call
                        </button>
                    </div>
                </div>
            </div>

            <!-- Call ended / result card -->
            <div id="callEndedCard" class="card shadow-sm d-none">
                <div class="card-body p-4 text-center">
                    <div id="endedIcon" class="mb-3">
                        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center"
                             style="width:80px;height:80px;font-size:2rem;">
                            <i class="fas fa-phone-slash"></i>
                        </div>
                    </div>
                    <h5 id="endedTitle">Call Ended</h5>
                    <p id="endedMessage" class="text-muted">Thank you for calling.</p>
                    <button type="button" id="callAgainBtn" class="btn btn-success mt-2">
                        <i class="fas fa-redo me-2"></i>Call Again
                    </button>
                </div>
            </div>

            <?php if ($office_phone): ?>
            <!-- Alternative: regular phone call -->
            <div class="card mt-4 border-0 bg-light">
                <div class="card-body py-3 text-center">
                    <p class="mb-1 text-muted small">Prefer a regular phone call?</p>
                    <a href="tel:<?php echo htmlspecialchars($office_phone); ?>" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($office_phone); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
$extra_js = '
<script>
(function () {
    "use strict";

    var BASE_URL = ' . json_encode(BASE_URL) . ';

    // ─── State ──────────────────────────────────────────────────────────────
    var callId        = null;
    var sessionToken  = null;
    var pc            = null;          // RTCPeerConnection
    var localStream   = null;
    var isMuted       = false;
    var pollTimer     = null;
    var timerInterval = null;
    var timerSeconds  = 0;
    var iceCandidateQueue = [];
    var iceFlushTimer = null;

    // ICE candidates collected before call_id is known
    var pendingCandidates = [];

    var STUN_CONFIG = {
        iceServers: [
            { urls: "stun:stun.l.google.com:19302" },
            { urls: "stun:stun1.l.google.com:19302" }
        ]
    };

    // ─── UI helpers ─────────────────────────────────────────────────────────
    function show(id)  { var el = document.getElementById(id); if (el) el.classList.remove("d-none"); }
    function hide(id)  { var el = document.getElementById(id); if (el) el.classList.add("d-none"); }
    function setText(id, txt) { var el = document.getElementById(id); if (el) el.textContent = txt; }

    function showEndedState(title, msg, isError) {
        hide("callSetupCard");
        hide("callActiveCard");
        show("callEndedCard");
        setText("endedTitle", title);
        setText("endedMessage", msg);
        var iconEl = document.querySelector("#endedIcon .rounded-circle");
        if (iconEl) {
            iconEl.className = "rounded-circle text-white d-inline-flex align-items-center justify-content-center";
            iconEl.style.cssText = "width:80px;height:80px;font-size:2rem;";
            iconEl.classList.add(isError ? "bg-danger" : "bg-secondary");
        }
    }

    // ─── Timer ──────────────────────────────────────────────────────────────
    function startTimer() {
        timerSeconds = 0;
        timerInterval = setInterval(function () {
            timerSeconds++;
            var m = Math.floor(timerSeconds / 60);
            var s = timerSeconds % 60;
            setText("callTimer", m + ":" + (s < 10 ? "0" : "") + s);
        }, 1000);
    }
    function stopTimer() {
        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    }

    // ─── ICE candidate sending (batched every 500 ms) ───────────────────────
    function scheduleIceSend(candidate) {
        iceCandidateQueue.push(candidate);
        if (!iceFlushTimer) {
            iceFlushTimer = setTimeout(flushIceCandidates, 500);
        }
    }
    function flushIceCandidates() {
        iceFlushTimer = null;
        if (!callId || iceCandidateQueue.length === 0) return;
        var candidates = iceCandidateQueue.splice(0);
        candidates.forEach(function (c) {
            var fd = new FormData();
            fd.append("call_id",   callId);
            fd.append("token",     sessionToken || "");
            fd.append("role",      "caller");
            fd.append("candidate", JSON.stringify(c));
            fetch(BASE_URL + "/api/call/signal.php", { method: "POST", body: fd }).catch(function(){});
        });
    }

    // ─── WebRTC setup ────────────────────────────────────────────────────────
    function setupPeerConnection() {
        pc = new RTCPeerConnection(STUN_CONFIG);

        pc.onicecandidate = function (event) {
            if (event.candidate) {
                scheduleIceSend(event.candidate.toJSON ? event.candidate.toJSON() : event.candidate);
            }
        };

        pc.ontrack = function (event) {
            var audio = document.getElementById("remoteAudio");
            if (audio) {
                if (event.streams && event.streams[0]) {
                    audio.srcObject = event.streams[0];
                } else {
                    var stream = new MediaStream();
                    stream.addTrack(event.track);
                    audio.srcObject = stream;
                }
            }
        };

        pc.onconnectionstatechange = function () {
            if (pc.connectionState === "connected") {
                setText("callStatusText", "Connected");
                setText("callSubText", "You are speaking with our admin team.");
                var icon = document.getElementById("callIcon");
                if (icon) { icon.className = "fas fa-phone"; }
                hide("queueInfo");
                hide("cancelControls");
                show("callControls");
                startTimer();
            }
            if (pc.connectionState === "disconnected" || pc.connectionState === "failed" || pc.connectionState === "closed") {
                cleanupCall();
                showEndedState("Call Ended", "The call has ended. Thank you for contacting us.", false);
            }
        };
    }

    // ─── Start call ─────────────────────────────────────────────────────────
    document.getElementById("startCallBtn").addEventListener("click", function () {
        var phone = document.getElementById("callerPhone").value.trim();
        var name  = document.getElementById("callerName").value.trim();

        if (!phone) {
            alert("Please enter your phone number to proceed.");
            document.getElementById("callerPhone").focus();
            return;
        }

        hide("micWarning");

        navigator.mediaDevices.getUserMedia({ audio: true, video: false })
            .then(function (stream) {
                localStream = stream;
                startCallingProcess(phone, name, stream);
            })
            .catch(function () {
                show("micWarning");
            });
    });

    function startCallingProcess(phone, name, stream) {
        hide("callSetupCard");
        show("callActiveCard");
        setText("callStatusText", "Connecting…");
        setText("callSubText", "Creating call session…");
        hide("callControls");
        show("cancelControls");
        hide("queueInfo");

        setupPeerConnection();
        stream.getTracks().forEach(function (t) { pc.addTrack(t, stream); });

        // Create SDP offer
        pc.createOffer()
            .then(function (offer) { return pc.setLocalDescription(offer); })
            .then(function () {
                var fd = new FormData();
                fd.append("caller_phone", phone);
                fd.append("caller_name",  name || "");
                fd.append("offer_sdp",    JSON.stringify(pc.localDescription));

                return fetch(BASE_URL + "/api/call/initiate.php", { method: "POST", body: fd });
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || "Failed to start call");
                }
                callId       = data.call_id;
                sessionToken = data.session_token;

                setText("callStatusText", "Ringing…");
                setText("callSubText", "Waiting for an admin to answer.");
                show("queueInfo");
                setText("queueText", "Waiting for admin to answer…");

                // Flush any ICE candidates collected before call_id was known
                if (iceCandidateQueue.length > 0) { flushIceCandidates(); }

                startPolling();
            })
            .catch(function (err) {
                showEndedState("Connection Error", err.message || "Could not start call.", true);
                cleanupCall();
            });
    }

    // ─── Polling for admin answer ────────────────────────────────────────────
    function startPolling() {
        pollTimer = setInterval(pollCallStatus, 2000);
    }

    function pollCallStatus() {
        if (!sessionToken) return;
        fetch(BASE_URL + "/api/call/poll.php?token=" + encodeURIComponent(sessionToken))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;

                if (data.status === "active" && data.answer_sdp && !pc.remoteDescription) {
                    applyAnswer(data.answer_sdp, data.admin_ice || []);
                }
                if (data.status === "declined") {
                    stopPolling();
                    cleanupCall();
                    showEndedState("Call Declined", "The admin is currently unavailable. Please try again later.", false);
                }
                if (data.status === "ended") {
                    stopPolling();
                    cleanupCall();
                    showEndedState("Call Ended", "The call has ended.", false);
                }
                if (data.status === "missed") {
                    stopPolling();
                    cleanupCall();
                    showEndedState("No Answer", "No admin is available right now. Please try again or call on the phone.", false);
                }
            })
            .catch(function () { /* network glitch – keep polling */ });
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function applyAnswer(answerSdpJson, adminIceCandidates) {
        var answerSdp;
        try {
            answerSdp = JSON.parse(answerSdpJson);
        } catch(e) {
            return;
        }
        pc.setRemoteDescription(new RTCSessionDescription(answerSdp))
            .then(function () {
                // Apply ICE candidates from admin
                adminIceCandidates.forEach(function (c) {
                    pc.addIceCandidate(new RTCIceCandidate(c)).catch(function(){});
                });
                stopPolling();
                // Keep a slow poll to pick up any late ICE candidates
                pollTimer = setInterval(pollLateIce, 3000);
            })
            .catch(function () {});
    }

    function pollLateIce() {
        if (!sessionToken) return;
        fetch(BASE_URL + "/api/call/poll.php?token=" + encodeURIComponent(sessionToken))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === "ended" || data.status === "declined") {
                    stopPolling();
                    cleanupCall();
                    showEndedState("Call Ended", "The call has ended.", false);
                    return;
                }
                // Add any newly arrived ICE candidates
                var existing = (window._adminIceCount || 0);
                var candidates = data.admin_ice || [];
                if (candidates.length > existing) {
                    candidates.slice(existing).forEach(function (c) {
                        pc.addIceCandidate(new RTCIceCandidate(c)).catch(function(){});
                    });
                    window._adminIceCount = candidates.length;
                }
            })
            .catch(function () {});
    }

    // ─── Mute toggle ────────────────────────────────────────────────────────
    document.getElementById("muteBtn").addEventListener("click", function () {
        if (!localStream) return;
        isMuted = !isMuted;
        localStream.getAudioTracks().forEach(function (t) { t.enabled = !isMuted; });
        document.getElementById("muteIcon").className = isMuted ? "fas fa-microphone-slash" : "fas fa-microphone";
        setText("muteText", isMuted ? "Unmute" : "Mute");
        this.className = isMuted ? "btn btn-warning me-2" : "btn btn-outline-secondary me-2";
    });

    // ─── End / Cancel call ───────────────────────────────────────────────────
    document.getElementById("endCallBtn").addEventListener("click", endCall);
    document.getElementById("cancelCallBtn").addEventListener("click", endCall);

    function endCall() {
        if (callId) {
            var fd = new FormData();
            fd.append("call_id", callId);
            fd.append("token",   sessionToken || "");
            fd.append("reason",  "ended");
            fetch(BASE_URL + "/api/call/decline.php", { method: "POST", body: fd }).catch(function(){});
        }
        cleanupCall();
        showEndedState("Call Ended", "You have ended the call.", false);
    }

    function cleanupCall() {
        stopPolling();
        stopTimer();
        if (localStream) { localStream.getTracks().forEach(function (t) { t.stop(); }); localStream = null; }
        if (pc) { pc.close(); pc = null; }
        callId = null; sessionToken = null; isMuted = false;
    }

    // ─── Call again ──────────────────────────────────────────────────────────
    document.getElementById("callAgainBtn").addEventListener("click", function () {
        hide("callEndedCard");
        show("callSetupCard");
        setText("callTimer", "0:00");
    });

})();
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>

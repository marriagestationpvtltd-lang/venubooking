<?php
$page_title       = 'Portfolio';
$page_description = 'Browse our event portfolio by category — weddings, receptions, birthdays, corporate events and more. See our work in action.';
$page_keywords    = 'portfolio, event portfolio, wedding portfolio, event photography Nepal, venue decoration';
require_once __DIR__ . '/includes/header.php';

// Data
$work_categories = getWorkPhotosByCategory();
$office_whatsapp = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);

$work_categories_js = [];
if (!empty($work_categories)) {
    foreach ($work_categories as $cat_name => $cat_photos) {
        $work_categories_js[] = [
            'name'   => $cat_name,
            'photos' => array_map(function($img) {
                return [
                    'src'   => $img['image_url'],
                    'title' => $img['title'],
                    'desc'  => $img['description'] ?? '',
                ];
            }, $cat_photos),
        ];
    }
}
$work_categories_json = json_encode($work_categories_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<!-- Page Hero -->
<div class="page-hero-bar bg-success text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Portfolio</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <h1 class="h3 mb-0 fw-bold"><i class="fas fa-folder-open me-2"></i>हाम्रो काम</h1>
            <div class="section-share-wrap">
                <button class="section-share-btn" type="button" title="Share" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-share-alt" aria-hidden="true"></i>
                    <span>Share</span>
                </button>
                <div class="section-share-dropdown" role="menu" aria-label="Share options">
                    <button class="share-opt share-copy" type="button" role="menuitem">
                        <i class="fas fa-link" aria-hidden="true"></i> Copy link
                    </button>
                    <a class="share-opt share-whatsapp" href="#" role="menuitem" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i> Share on WhatsApp
                    </a>
                    <a class="share-opt share-facebook" href="#" role="menuitem" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-facebook-f" aria-hidden="true"></i> Share on Facebook
                    </a>
                </div>
            </div>
        </div>
        <p class="mb-0 mt-1 text-white-75 small">Portfolio — Browse our events by category</p>
    </div>
</div>

<?php if (!empty($work_categories)): ?>
<!-- Our Work – Folder Gallery Section -->
<section class="work-photos-section py-5" id="section-work">
    <div class="container">
        <!-- Marquee wrapper -->
        <div class="work-folder-marquee">
            <div class="work-folder-track" id="workFolderTrack">
                <?php
                $cat_keys = array_keys($work_categories);
                for ($wf_pass = 0; $wf_pass < 2; $wf_pass++):
                    foreach ($work_categories as $cat_name => $cat_photos):
                        $preview   = $cat_photos[0];
                        $cat_count = count($cat_photos);
                        $cat_index = array_search($cat_name, $cat_keys);
                        $is_dup    = ($wf_pass === 1);
                ?>
                <div class="work-folder-card"
                     data-cat-index="<?php echo $cat_index; ?>"
                     <?php if ($is_dup): ?>
                     aria-hidden="true" tabindex="-1"
                     <?php else: ?>
                     role="button" tabindex="0"
                     aria-label="<?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?> (<?php echo $cat_count; ?> photo<?php echo $cat_count !== 1 ? 's' : ''; ?>)"
                     <?php endif; ?>>
                    <div class="work-folder-img-wrap">
                        <img src="<?php echo htmlspecialchars($preview['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($preview['title'], ENT_QUOTES, 'UTF-8'); ?>"
                             class="work-folder-img"
                             loading="lazy"
                             draggable="false">
                        <div class="work-folder-overlay">
                            <i class="fas fa-folder-open work-folder-icon"></i>
                        </div>
                    </div>
                    <div class="work-folder-info">
                        <div class="work-folder-title">
                            <i class="fas fa-folder me-2 text-warning"></i><?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="work-folder-count">
                            <i class="fas fa-images me-1"></i><?php echo $cat_count; ?> Photo<?php echo $cat_count !== 1 ? 's' : ''; ?>
                        </div>
                    </div>
                </div>
                <?php
                    endforeach;
                endfor;
                ?>
            </div>
        </div>
    </div>
</section>
<?php else: ?>
<div class="container py-5 text-center">
    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
    <h3 class="text-muted">No portfolio items available at the moment.</h3>
    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-success mt-3">
        <i class="fas fa-home me-1"></i> Back to Home
    </a>
</div>
<?php endif; ?>

<!-- Portfolio Slideshow Modal -->
<div id="portfolioModal" class="portfolio-modal" role="dialog" aria-modal="true" aria-label="Portfolio slideshow">
    <div class="portfolio-modal-backdrop"></div>
    <div class="portfolio-modal-content">
        <button class="portfolio-modal-close" id="portfolioModalClose" aria-label="Close slideshow">
            <i class="fas fa-times"></i>
        </button>
        <div class="portfolio-modal-img-wrap">
            <button class="portfolio-modal-nav portfolio-modal-prev" id="portfolioModalPrev" aria-label="Previous photo">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="portfolio-modal-img-container" id="portfolioImgContainer">
                <img id="portfolioModalImg" src="" alt="" class="portfolio-modal-img" draggable="false">
            </div>
            <button class="portfolio-modal-nav portfolio-modal-next" id="portfolioModalNext" aria-label="Next photo">
                <i class="fas fa-chevron-right"></i>
            </button>
            <div class="portfolio-modal-zoom-controls">
                <button class="portfolio-zoom-btn" id="portfolioZoomIn" aria-label="Zoom in" title="Zoom In">
                    <i class="fas fa-search-plus"></i>
                </button>
                <button class="portfolio-zoom-btn" id="portfolioZoomOut" aria-label="Zoom out" title="Zoom Out">
                    <i class="fas fa-search-minus"></i>
                </button>
                <button class="portfolio-zoom-btn" id="portfolioZoomReset" aria-label="Reset zoom" title="Reset Zoom">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
            <div class="portfolio-modal-zoom-hint" id="portfolioZoomHint">Double-click or pinch to zoom</div>
        </div>
        <div class="portfolio-modal-footer">
            <div class="portfolio-modal-caption">
                <span id="portfolioModalTitle"></span>
                <span id="portfolioModalDesc" class="portfolio-modal-desc-text"></span>
            </div>
            <div class="portfolio-modal-counter" id="portfolioModalCounter"></div>
        </div>
        <div class="portfolio-modal-thumbs" id="portfolioModalThumbs"></div>
    </div>
</div>

<!-- Floating WhatsApp Button -->
<?php if (!empty($clean_office_whatsapp)): ?>
<a href="https://wa.me/<?php echo htmlspecialchars($clean_office_whatsapp, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode('Hello! I would like to inquire about your event portfolio and services. Please help me.'); ?>"
   class="floating-wa-btn"
   target="_blank" rel="noopener noreferrer"
   aria-label="Contact us on WhatsApp"
   title="Chat on WhatsApp">
    <span class="floating-wa-pulse" aria-hidden="true"></span>
    <i class="fab fa-whatsapp wa-fab-icon"></i>
    <span class="wa-fab-text">Chat with Us</span>
</a>
<?php endif; ?>

<button class="scroll-top-fab" id="scrollTopFab" aria-label="Scroll to top" title="Back to top">
    <i class="fas fa-chevron-up"></i>
</button>

<?php
$work_categories_json_output = $work_categories_json;
$extra_js = '
<script>
(function() {
    var allCategories = ' . $work_categories_json_output . ';
    var modal        = document.getElementById("portfolioModal");
    var modalImg     = document.getElementById("portfolioModalImg");
    var imgContainer = document.getElementById("portfolioImgContainer");
    var modalTitle   = document.getElementById("portfolioModalTitle");
    var modalDesc    = document.getElementById("portfolioModalDesc");
    var modalCounter = document.getElementById("portfolioModalCounter");
    var thumbsEl     = document.getElementById("portfolioModalThumbs");
    var zoomHint     = document.getElementById("portfolioZoomHint");
    var currentPhotos=[],current=0,autoTimer=null,AUTO_INTERVAL=4000;
    var zoomLevel=1,minZoom=1,maxZoom=4,zoomStep=0.5,panX=0,panY=0,isDragging=false,startX=0,startY=0,pinchStartDist=0,pinchStartZoom=1;

    function resetZoom(){zoomLevel=1;panX=0;panY=0;applyTransform();imgContainer.classList.remove("zoomed");}
    function applyTransform(){modalImg.style.transform="scale("+zoomLevel+") translate("+panX+"px,"+panY+"px)";}
    function zoomIn(){stopAuto();zoomLevel=Math.min(maxZoom,zoomLevel+zoomStep);if(zoomLevel>1)imgContainer.classList.add("zoomed");applyTransform();hideZoomHint();}
    function zoomOut(){zoomLevel=Math.max(minZoom,zoomLevel-zoomStep);if(zoomLevel<=1){resetZoom();startAuto();}else{applyTransform();}}
    function zoomToPoint(cx,cy,nz){var rect=imgContainer.getBoundingClientRect();var ox=(cx-rect.left-rect.width/2)/zoomLevel,oy=(cy-rect.top-rect.height/2)/zoomLevel;var oz=zoomLevel;zoomLevel=Math.max(minZoom,Math.min(maxZoom,nz));if(zoomLevel>1){stopAuto();var sd=zoomLevel/oz;panX=panX-ox*(sd-1)/zoomLevel;panY=panY-oy*(sd-1)/zoomLevel;imgContainer.classList.add("zoomed");}else{resetZoom();startAuto();return;}applyTransform();hideZoomHint();}
    function hideZoomHint(){if(zoomHint)zoomHint.style.display="none";}
    function showZoomHint(){if(zoomHint)zoomHint.style.display="block";}
    imgContainer.addEventListener("dblclick",function(e){e.preventDefault();if(zoomLevel>1){resetZoom();startAuto();}else{stopAuto();zoomToPoint(e.clientX,e.clientY,2.5);}});
    imgContainer.addEventListener("wheel",function(e){e.preventDefault();zoomToPoint(e.clientX,e.clientY,zoomLevel+(e.deltaY>0?-zoomStep:zoomStep));},{passive:false});
    imgContainer.addEventListener("mousedown",function(e){if(zoomLevel<=1)return;isDragging=true;startX=e.clientX;startY=e.clientY;modalImg.classList.add("zooming");e.preventDefault();});
    document.addEventListener("mousemove",function(e){if(!isDragging||!modal.classList.contains("active"))return;panX+=(e.clientX-startX)/zoomLevel;panY+=(e.clientY-startY)/zoomLevel;startX=e.clientX;startY=e.clientY;applyTransform();});
    document.addEventListener("mouseup",function(){if(modal.classList.contains("active")){isDragging=false;modalImg.classList.remove("zooming");}});
    function getPinchDist(t){var dx=t[0].clientX-t[1].clientX,dy=t[0].clientY-t[1].clientY;return Math.sqrt(dx*dx+dy*dy);}
    imgContainer.addEventListener("touchstart",function(e){if(e.touches.length===2){pinchStartDist=getPinchDist(e.touches);pinchStartZoom=zoomLevel;}else if(e.touches.length===1&&zoomLevel>1){isDragging=true;startX=e.touches[0].clientX;startY=e.touches[0].clientY;modalImg.classList.add("zooming");}},{passive:true});
    imgContainer.addEventListener("touchmove",function(e){if(e.touches.length===2){e.preventDefault();var d=getPinchDist(e.touches);zoomToPoint((e.touches[0].clientX+e.touches[1].clientX)/2,(e.touches[0].clientY+e.touches[1].clientY)/2,pinchStartZoom*(d/pinchStartDist));}else if(e.touches.length===1&&isDragging&&zoomLevel>1){panX+=(e.touches[0].clientX-startX)/zoomLevel;panY+=(e.touches[0].clientY-startY)/zoomLevel;startX=e.touches[0].clientX;startY=e.touches[0].clientY;applyTransform();}},{passive:false});
    imgContainer.addEventListener("touchend",function(){isDragging=false;modalImg.classList.remove("zooming");},{passive:true});
    document.getElementById("portfolioZoomIn").addEventListener("click",function(e){e.stopPropagation();zoomIn();});
    document.getElementById("portfolioZoomOut").addEventListener("click",function(e){e.stopPropagation();zoomOut();});
    document.getElementById("portfolioZoomReset").addEventListener("click",function(e){e.stopPropagation();resetZoom();startAuto();});
    function buildThumbs(photos){thumbsEl.innerHTML="";photos.forEach(function(photo,idx){var img=document.createElement("img");img.src=photo.src;img.alt=photo.title||"";img.className="portfolio-modal-thumb";img.dataset.index=idx;img.loading="lazy";img.draggable=false;img.addEventListener("click",function(){stopAuto();resetZoom();showSlide(idx);startAuto();});thumbsEl.appendChild(img);});}
    function showSlide(idx){if(!currentPhotos.length)return;current=((idx%currentPhotos.length)+currentPhotos.length)%currentPhotos.length;var photo=currentPhotos[current];modalImg.src=photo.src;modalImg.alt=photo.title||"";modalTitle.textContent=photo.title||"";modalDesc.textContent=photo.desc||"";modalCounter.textContent=(current+1)+" / "+currentPhotos.length;resetZoom();showZoomHint();var thumbs=Array.from(thumbsEl.querySelectorAll(".portfolio-modal-thumb"));thumbs.forEach(function(t){t.classList.remove("active");});if(thumbs[current]){thumbs[current].classList.add("active");thumbs[current].scrollIntoView({behavior:"smooth",block:"nearest",inline:"center"});}}
    function openFolder(ci,si){var cat=allCategories[ci];if(!cat)return;modal.classList.remove("closing");currentPhotos=cat.photos;buildThumbs(currentPhotos);current=si||0;modal.classList.add("active");document.body.classList.add("modal-open");showSlide(current);startAuto();}
    var MODAL_CLOSE_DURATION=260,closeTimer=null;
    function closeModal(){clearTimeout(closeTimer);stopAuto();resetZoom();document.body.classList.remove("modal-open");modal.classList.add("closing");closeTimer=setTimeout(function(){modal.classList.remove("active");modal.classList.remove("closing");modalImg.src="";currentPhotos=[];thumbsEl.innerHTML="";},MODAL_CLOSE_DURATION);}
    function prevSlide(){stopAuto();resetZoom();showSlide(current-1);startAuto();}
    function nextSlide(){stopAuto();resetZoom();showSlide(current+1);startAuto();}
    function startAuto(){stopAuto();if(currentPhotos.length>1){autoTimer=setInterval(function(){showSlide(current+1);},AUTO_INTERVAL);}}
    function stopAuto(){if(autoTimer){clearInterval(autoTimer);autoTimer=null;}}

    // Marquee speed
    var wfTrack=document.getElementById("workFolderTrack");
    if(wfTrack){var nc=allCategories.length;wfTrack.style.setProperty("--wf-duration",Math.max(10,nc*5)+"s");}

    // Custom cursor + drag-to-scroll
    (function(){
        var wfMarquee=document.querySelector(".work-folder-marquee");
        if(!wfMarquee||!wfTrack)return;
        var wfCursor=document.createElement("div");wfCursor.className="wf-cursor";wfCursor.setAttribute("aria-hidden","true");wfMarquee.appendChild(wfCursor);
        var isDg=false,didDrag=false,dragStartX=0,dragTrackX=0,idleTimer=null;
        function getTrackX(){var s=window.getComputedStyle(wfTrack).transform;if(!s||s==="none")return 0;var m=s.match(/matrix\([^,]+,[^,]+,[^,]+,[^,]+,([^,]+),/);if(m)return parseFloat(m[1])||0;var m3=s.match(/matrix3d\([^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,([^,]+),/);return m3?(parseFloat(m3[1])||0):0;}
        function halfWidth(){return wfTrack.offsetWidth/2;}
        function resumeMarquee(){var x=getTrackX(),hw=halfWidth();if(hw<=0)return;x=Math.max(-hw,Math.min(0,x));var p=-x/hw,dur=parseFloat(window.getComputedStyle(wfTrack).animationDuration)||30;wfTrack.style.animationDelay=-(p*dur)+"s";wfTrack.style.transform="";wfTrack.style.animationPlayState="";}
        wfMarquee.addEventListener("mousemove",function(e){var rect=wfMarquee.getBoundingClientRect();wfCursor.style.left=(e.clientX-rect.left)+"px";wfCursor.style.top=(e.clientY-rect.top)+"px";if(!isDg){wfCursor.classList.add("wf-cursor--visible");clearTimeout(idleTimer);idleTimer=setTimeout(function(){if(!isDg)wfCursor.classList.remove("wf-cursor--visible");},800);}if(!isDg)return;var dx=e.clientX-dragStartX;if(Math.abs(dx)>5)didDrag=true;var hw=halfWidth(),newX=Math.max(-hw,Math.min(0,dragTrackX+dx));wfTrack.style.transform="translateX("+newX+"px)";});
        wfMarquee.addEventListener("mouseenter",function(){clearTimeout(idleTimer);wfCursor.classList.add("wf-cursor--visible");idleTimer=setTimeout(function(){if(!isDg)wfCursor.classList.remove("wf-cursor--visible");},800);});
        wfMarquee.addEventListener("mouseleave",function(){clearTimeout(idleTimer);wfCursor.classList.remove("wf-cursor--visible");if(isDg){isDg=false;wfCursor.classList.remove("wf-cursor--grabbing");resumeMarquee();}didDrag=false;});
        wfMarquee.addEventListener("mousedown",function(e){if(e.button!==0)return;isDg=true;didDrag=false;dragStartX=e.clientX;dragTrackX=getTrackX();wfTrack.style.animationPlayState="paused";clearTimeout(idleTimer);wfCursor.classList.add("wf-cursor--visible");wfCursor.classList.add("wf-cursor--grabbing");e.preventDefault();});
        document.addEventListener("mouseup",function(){if(!isDg)return;isDg=false;wfCursor.classList.remove("wf-cursor--grabbing");resumeMarquee();});
        wfMarquee.addEventListener("click",function(e){if(didDrag){e.stopPropagation();e.preventDefault();didDrag=false;}},true);
    }());

    document.querySelectorAll(".work-folder-card:not([aria-hidden])").forEach(function(card){function open(){openFolder(parseInt(card.dataset.catIndex,10),0);}card.addEventListener("click",open);card.addEventListener("keydown",function(e){if(e.key==="Enter"||e.key===" "){e.preventDefault();open();}});});
    document.querySelectorAll(".work-folder-card[aria-hidden]").forEach(function(card){card.addEventListener("click",function(){openFolder(parseInt(card.dataset.catIndex,10),0);});});
    document.getElementById("portfolioModalClose").addEventListener("click",closeModal);
    document.getElementById("portfolioModalPrev").addEventListener("click",prevSlide);
    document.getElementById("portfolioModalNext").addEventListener("click",nextSlide);
    modal.querySelector(".portfolio-modal-backdrop").addEventListener("click",closeModal);
    document.addEventListener("keydown",function(e){if(!modal.classList.contains("active"))return;if(e.key==="Escape")closeModal();if(e.key==="ArrowLeft")prevSlide();if(e.key==="ArrowRight")nextSlide();if(e.key==="+"||e.key==="=")zoomIn();if(e.key==="-")zoomOut();if(e.key==="0"){resetZoom();startAuto();}});
    var swipeStartX=0;
    modal.addEventListener("touchstart",function(e){if(zoomLevel<=1)swipeStartX=e.touches[0].pageX;},{passive:true});
    modal.addEventListener("touchend",function(e){if(zoomLevel>1)return;var dx=e.changedTouches[0].pageX-swipeStartX;if(Math.abs(dx)>50){dx<0?nextSlide():prevSlide();}},{passive:true});
    modal.querySelector(".portfolio-modal-img-wrap").addEventListener("mouseenter",function(){if(zoomLevel<=1)stopAuto();});
    modal.querySelector(".portfolio-modal-img-wrap").addEventListener("mouseleave",function(){if(zoomLevel<=1)startAuto();});
})();
</script>
<script>
(function() {
    var btn = document.getElementById("scrollTopFab");
    if (!btn) return;
    var ticking = false;
    window.addEventListener("scroll", function() {
        if (!ticking) { requestAnimationFrame(function() { btn.classList.toggle("visible", window.scrollY > 400); ticking = false; }); ticking = true; }
    }, { passive: true });
    btn.addEventListener("click", function() { window.scrollTo({ top: 0, behavior: "smooth" }); });
}());
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>

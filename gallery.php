<?php
$page_title       = 'Gallery';
$page_description = 'Browse our photo gallery — memorable moments from weddings, receptions, birthdays, and corporate events hosted at our venues.';
$page_keywords    = 'gallery, wedding photos, event photos, venue gallery, Nepal events';
require_once __DIR__ . '/includes/header.php';

// Data
$gallery_cards = getImagesByCards('gallery');
$office_whatsapp = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);

$gallery_cards_json = '[]';
if (!empty($gallery_cards)) {
    $gallery_cards_json = json_encode(array_map(function($card) {
        return array_map(function($img) {
            return [
                'src'   => $img['image_url'],
                'title' => $img['title'],
                'desc'  => $img['description'] ?? '',
            ];
        }, $card);
    }, $gallery_cards), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
?>

<!-- Page Hero -->
<div class="page-hero-bar bg-success text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Gallery</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 fw-bold"><i class="fas fa-images me-2"></i>हाम्रा यादगार पलहरू</h1>
        <p class="mb-0 mt-1 text-white-75 small">Our Gallery — Moments we are proud to capture</p>
    </div>
</div>

<?php if (!empty($gallery_cards)): ?>
<!-- Gallery Section -->
<section class="gallery-section py-5 bg-light" id="section-gallery">
    <div class="container">
        <div class="photo-cards-grid">
            <?php foreach ($gallery_cards as $ci => $card):
                $preview = $card[0];
                $total   = count($card);
                $extra   = $total - 1;
            ?>
            <div class="photo-card" role="button" tabindex="0"
                 data-card-index="<?php echo $ci; ?>"
                 aria-label="View card <?php echo $ci + 1; ?> (<?php echo $total; ?> photo<?php echo $total !== 1 ? 's' : ''; ?>)">

                <div class="photo-card-img-wrap">
                    <img src="<?php echo htmlspecialchars($preview['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($preview['title'], ENT_QUOTES, 'UTF-8'); ?>"
                         class="photo-card-img"
                         loading="lazy">

                    <?php if ($extra > 0): ?>
                    <span class="photo-card-badge">
                        <i class="fas fa-images me-1"></i>+<?php echo $extra; ?> Photo<?php echo $extra !== 1 ? 's' : ''; ?>
                    </span>
                    <?php endif; ?>

                    <div class="photo-card-overlay">
                        <i class="fas fa-search-plus photo-card-zoom-icon"></i>
                    </div>
                </div>

                <?php if (!empty($preview['title'])): ?>
                <div class="photo-card-caption">
                    <?php echo htmlspecialchars($preview['title'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php else: ?>
<div class="container py-5 text-center">
    <i class="fas fa-images fa-3x text-muted mb-3"></i>
    <h3 class="text-muted">No gallery photos available at the moment.</h3>
    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-success mt-3">
        <i class="fas fa-home me-1"></i> Back to Home
    </a>
</div>
<?php endif; ?>

<!-- Photo Card Modal -->
<div id="photoCardModal" class="photo-card-modal" role="dialog" aria-modal="true" aria-label="Photo gallery">
    <div class="photo-card-modal-backdrop"></div>
    <div class="photo-card-modal-content">
        <button class="photo-card-modal-close" id="photoCardModalClose" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
        <div class="photo-card-modal-img-wrap">
            <button class="photo-card-modal-nav photo-card-modal-prev" id="photoCardModalPrev" aria-label="Previous photo">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="photo-card-modal-img-container" id="photoCardImgContainer">
                <img id="photoCardModalImg" src="" alt="" class="photo-card-modal-img" draggable="false">
            </div>
            <button class="photo-card-modal-nav photo-card-modal-next" id="photoCardModalNext" aria-label="Next photo">
                <i class="fas fa-chevron-right"></i>
            </button>
            <div class="photo-card-modal-zoom-controls">
                <button class="photo-card-zoom-btn" id="photoCardZoomIn" aria-label="Zoom in" title="Zoom In">
                    <i class="fas fa-search-plus"></i>
                </button>
                <button class="photo-card-zoom-btn" id="photoCardZoomOut" aria-label="Zoom out" title="Zoom Out">
                    <i class="fas fa-search-minus"></i>
                </button>
                <button class="photo-card-zoom-btn" id="photoCardZoomReset" aria-label="Reset zoom" title="Reset Zoom">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
            <div class="photo-card-modal-zoom-hint" id="photoCardZoomHint">Double-click or pinch to zoom</div>
        </div>
        <div class="photo-card-modal-footer">
            <div class="photo-card-modal-caption">
                <span id="photoCardModalTitle"></span>
                <span id="photoCardModalDesc" class="photo-card-modal-desc-text"></span>
            </div>
            <div class="photo-card-modal-counter" id="photoCardModalCounter"></div>
        </div>
        <div class="photo-card-modal-thumbs" id="photoCardModalThumbs"></div>
    </div>
</div>

<!-- Floating WhatsApp Button -->
<?php if (!empty($clean_office_whatsapp)): ?>
<a href="https://wa.me/<?php echo htmlspecialchars($clean_office_whatsapp, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode('Hello! I would like to book a venue. Please help me.'); ?>"
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
$gallery_json_output = $gallery_cards_json;
$extra_js = '
<script>
(function() {
    var allCards = ' . $gallery_json_output . ';
    var modal        = document.getElementById("photoCardModal");
    var modalImg     = document.getElementById("photoCardModalImg");
    var imgContainer = document.getElementById("photoCardImgContainer");
    var modalTitle   = document.getElementById("photoCardModalTitle");
    var modalDesc    = document.getElementById("photoCardModalDesc");
    var modalCnt     = document.getElementById("photoCardModalCounter");
    var thumbsEl     = document.getElementById("photoCardModalThumbs");
    var zoomHint     = document.getElementById("photoCardZoomHint");
    var cardPhotos   = [];
    var current      = 0;
    var zoomLevel = 1, minZoom = 1, maxZoom = 4, zoomStep = 0.5;
    var panX = 0, panY = 0, isDragging = false, startX = 0, startY = 0;
    var pinchStartDist = 0, pinchStartZoom = 1;

    function resetZoom() { zoomLevel=1; panX=0; panY=0; applyTransform(); imgContainer.classList.remove("zoomed"); }
    function applyTransform() { modalImg.style.transform="scale("+zoomLevel+") translate("+panX+"px,"+panY+"px)"; }
    function zoomIn() { zoomLevel=Math.min(maxZoom,zoomLevel+zoomStep); if(zoomLevel>1)imgContainer.classList.add("zoomed"); applyTransform(); hideZoomHint(); }
    function zoomOut() { zoomLevel=Math.max(minZoom,zoomLevel-zoomStep); if(zoomLevel<=1){resetZoom();}else{applyTransform();} }
    function zoomToPoint(cx,cy,nz) {
        var rect=imgContainer.getBoundingClientRect();
        var ox=(cx-rect.left-rect.width/2)/zoomLevel, oy=(cy-rect.top-rect.height/2)/zoomLevel;
        var oz=zoomLevel; zoomLevel=Math.max(minZoom,Math.min(maxZoom,nz));
        if(zoomLevel>1){var sd=zoomLevel/oz;panX=panX-ox*(sd-1)/zoomLevel;panY=panY-oy*(sd-1)/zoomLevel;imgContainer.classList.add("zoomed");}else{resetZoom();return;}
        applyTransform(); hideZoomHint();
    }
    function hideZoomHint(){if(zoomHint)zoomHint.style.display="none";}
    function showZoomHint(){if(zoomHint)zoomHint.style.display="block";}
    imgContainer.addEventListener("dblclick",function(e){e.preventDefault();if(zoomLevel>1){resetZoom();}else{zoomToPoint(e.clientX,e.clientY,2.5);}});
    imgContainer.addEventListener("wheel",function(e){e.preventDefault();zoomToPoint(e.clientX,e.clientY,zoomLevel+(e.deltaY>0?-zoomStep:zoomStep));},{passive:false});
    imgContainer.addEventListener("mousedown",function(e){if(zoomLevel<=1)return;isDragging=true;startX=e.clientX;startY=e.clientY;modalImg.classList.add("zooming");e.preventDefault();});
    document.addEventListener("mousemove",function(e){if(!isDragging||modal.style.display!=="flex")return;panX+=(e.clientX-startX)/zoomLevel;panY+=(e.clientY-startY)/zoomLevel;startX=e.clientX;startY=e.clientY;applyTransform();});
    document.addEventListener("mouseup",function(){if(modal.style.display!=="flex")return;isDragging=false;modalImg.classList.remove("zooming");});
    function getPinchDist(t){var dx=t[0].clientX-t[1].clientX,dy=t[0].clientY-t[1].clientY;return Math.sqrt(dx*dx+dy*dy);}
    imgContainer.addEventListener("touchstart",function(e){if(e.touches.length===2){pinchStartDist=getPinchDist(e.touches);pinchStartZoom=zoomLevel;}else if(e.touches.length===1&&zoomLevel>1){isDragging=true;startX=e.touches[0].clientX;startY=e.touches[0].clientY;modalImg.classList.add("zooming");}},{passive:true});
    imgContainer.addEventListener("touchmove",function(e){if(e.touches.length===2){e.preventDefault();var d=getPinchDist(e.touches);zoomToPoint((e.touches[0].clientX+e.touches[1].clientX)/2,(e.touches[0].clientY+e.touches[1].clientY)/2,pinchStartZoom*(d/pinchStartDist));}else if(e.touches.length===1&&isDragging&&zoomLevel>1){panX+=(e.touches[0].clientX-startX)/zoomLevel;panY+=(e.touches[0].clientY-startY)/zoomLevel;startX=e.touches[0].clientX;startY=e.touches[0].clientY;applyTransform();}},{passive:false});
    imgContainer.addEventListener("touchend",function(){isDragging=false;modalImg.classList.remove("zooming");},{passive:true});
    document.getElementById("photoCardZoomIn").addEventListener("click",function(e){e.stopPropagation();zoomIn();});
    document.getElementById("photoCardZoomOut").addEventListener("click",function(e){e.stopPropagation();zoomOut();});
    document.getElementById("photoCardZoomReset").addEventListener("click",function(e){e.stopPropagation();resetZoom();});
    function buildThumbs(photos){thumbsEl.innerHTML="";photos.forEach(function(photo,idx){var img=document.createElement("img");img.src=photo.src;img.alt=photo.title||"";img.className="photo-card-modal-thumb";img.dataset.index=idx;img.loading="lazy";img.draggable=false;img.addEventListener("click",function(){resetZoom();showSlide(idx);});thumbsEl.appendChild(img);});}
    function showSlide(idx){if(!cardPhotos.length)return;current=((idx%cardPhotos.length)+cardPhotos.length)%cardPhotos.length;var photo=cardPhotos[current];modalImg.src=photo.src;modalImg.alt=photo.title||"";modalTitle.textContent=photo.title||"";modalDesc.textContent=photo.desc||"";modalCnt.textContent=(current+1)+" / "+cardPhotos.length;resetZoom();showZoomHint();var thumbs=Array.from(thumbsEl.querySelectorAll(".photo-card-modal-thumb"));thumbs.forEach(function(t){t.classList.remove("active");});if(thumbs[current]){thumbs[current].classList.add("active");thumbs[current].scrollIntoView({behavior:"smooth",block:"nearest",inline:"center"});}}
    function openModal(ci,si){cardPhotos=allCards[ci]||[];buildThumbs(cardPhotos);modal.style.display="flex";document.body.classList.add("modal-open");showSlide(si||0);}
    function closeModal(){modal.style.display="none";document.body.classList.remove("modal-open");modalImg.src="";cardPhotos=[];thumbsEl.innerHTML="";resetZoom();}
    function prevSlide(){resetZoom();showSlide(current-1);}
    function nextSlide(){resetZoom();showSlide(current+1);}
    document.querySelectorAll(".photo-card").forEach(function(card){function open(){openModal(parseInt(card.dataset.cardIndex,10),0);}card.addEventListener("click",open);card.addEventListener("keydown",function(e){if(e.key==="Enter"||e.key===" "){e.preventDefault();open();}});});
    document.getElementById("photoCardModalClose").addEventListener("click",closeModal);
    document.getElementById("photoCardModalPrev").addEventListener("click",function(e){e.stopPropagation();prevSlide();});
    document.getElementById("photoCardModalNext").addEventListener("click",function(e){e.stopPropagation();nextSlide();});
    modal.querySelector(".photo-card-modal-backdrop").addEventListener("click",closeModal);
    document.addEventListener("keydown",function(e){if(modal.style.display!=="flex")return;if(e.key==="Escape")closeModal();if(e.key==="ArrowLeft")prevSlide();if(e.key==="ArrowRight")nextSlide();if(e.key==="+"||e.key==="=")zoomIn();if(e.key==="-")zoomOut();if(e.key==="0")resetZoom();});
    var swipeX=0;
    modal.addEventListener("touchstart",function(e){if(zoomLevel<=1)swipeX=e.touches[0].pageX;},{passive:true});
    modal.addEventListener("touchend",function(e){if(zoomLevel>1)return;var dx=e.changedTouches[0].pageX-swipeX;if(Math.abs(dx)>50){dx<0?nextSlide():prevSlide();}},{passive:true});
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

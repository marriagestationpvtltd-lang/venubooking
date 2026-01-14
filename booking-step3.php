<?php
/**
 * Booking Step 3: Menu Selection
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Check if Step 2 is completed
if (!isset($_SESSION['booking']['booking_date'])) {
    setFlashMessage('error', 'Please complete previous steps first');
    redirect('/index.php');
}

// Save Step 2 data if coming from form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token');
        redirect('/booking-step2.php');
    }
    
    $_SESSION['booking']['venue_id'] = (int)$_POST['venue_id'];
    $_SESSION['booking']['hall_id'] = (int)$_POST['hall_id'];
}

// Redirect if hall not selected
if (!isset($_SESSION['booking']['hall_id'])) {
    setFlashMessage('error', 'Please select a hall first');
    redirect('/booking-step2.php');
}

$csrfToken = generateCSRFToken();
$pageTitle = 'Select Menu - ' . APP_NAME;
include __DIR__ . '/includes/header.php';

$db = getDB();

// Get venue and hall details
$stmt = $db->prepare("SELECT v.venue_name, h.hall_name, h.base_price 
                      FROM venues v 
                      JOIN halls h ON v.id = h.venue_id 
                      WHERE h.id = :hall_id");
$stmt->bindParam(':hall_id', $_SESSION['booking']['hall_id'], PDO::PARAM_INT);
$stmt->execute();
$venueHall = $stmt->fetch();

// Get available menus for the hall
$menuStmt = $db->prepare("SELECT m.* FROM menus m
                          JOIN hall_menus hm ON m.id = hm.menu_id
                          WHERE hm.hall_id = :hall_id AND m.status = 'active'
                          ORDER BY m.price_per_person DESC");
$menuStmt->bindParam(':hall_id', $_SESSION['booking']['hall_id'], PDO::PARAM_INT);
$menuStmt->execute();
$menus = $menuStmt->fetchAll();
?>

<!-- Progress Indicator -->
<div class="container mt-4">
    <div class="step-indicator">
        <div class="step completed">
            <div class="step-number">1</div>
            <div class="step-title">Booking Details</div>
        </div>
        <div class="step completed">
            <div class="step-number">2</div>
            <div class="step-title">Venue & Hall</div>
        </div>
        <div class="step active">
            <div class="step-number">3</div>
            <div class="step-title">Select Menu</div>
        </div>
        <div class="step">
            <div class="step-number">4</div>
            <div class="step-title">Additional Services</div>
        </div>
        <div class="step">
            <div class="step-number">5</div>
            <div class="step-title">Confirm & Pay</div>
        </div>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <h2 style="color: var(--primary-dark); margin-bottom: 30px;">
                    <i class="fas fa-utensils"></i> Select Your Menu
                </h2>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> You can select one or multiple menus. Price will be calculated per person.
                </div>
                
                <?php if (empty($menus)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        No menus are currently available for this hall. Please contact us for custom menu options.
                    </div>
                    <a href="/booking-step2.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Hall Selection
                    </a>
                <?php else: ?>
                    <div class="menu-grid">
                        <?php foreach ($menus as $menu): 
                            // Get menu items
                            $itemStmt = $db->prepare("SELECT * FROM menu_items WHERE menu_id = :menu_id ORDER BY display_order ASC");
                            $itemStmt->bindParam(':menu_id', $menu['id'], PDO::PARAM_INT);
                            $itemStmt->execute();
                            $items = $itemStmt->fetchAll();
                        ?>
                        <div class="menu-card" data-menu-id="<?php echo $menu['id']; ?>">
                            <input type="checkbox" 
                                   class="menu-checkbox" 
                                   id="menu_<?php echo $menu['id']; ?>"
                                   data-menu-id="<?php echo $menu['id']; ?>"
                                   data-menu-name="<?php echo clean($menu['menu_name']); ?>"
                                   data-menu-price="<?php echo $menu['price_per_person']; ?>">
                            
                            <?php if ($menu['image']): ?>
                                <img src="/<?php echo UPLOAD_PATH_MENUS . clean($menu['image']); ?>" 
                                     alt="<?php echo clean($menu['menu_name']); ?>" 
                                     class="menu-img"
                                     onerror="this.src='/images/placeholder-menu.jpg'">
                            <?php else: ?>
                                <div class="menu-img" style="background: linear-gradient(135deg, var(--secondary-gold), var(--secondary-light)); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-utensils" style="font-size: 3rem; color: white;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="menu-info">
                                <h3 class="menu-name"><?php echo clean($menu['menu_name']); ?></h3>
                                
                                <?php if ($menu['category']): ?>
                                    <span class="badge badge-success mb-2"><?php echo clean($menu['category']); ?></span>
                                <?php endif; ?>
                                
                                <p class="menu-price">
                                    <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($menu['price_per_person'], 2); ?> / person
                                </p>
                                
                                <?php if ($menu['description']): ?>
                                    <p style="font-size: 0.9rem; color: var(--medium-gray);">
                                        <?php echo clean($menu['description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="menu-items">
                                    <strong>Includes <?php echo count($items); ?> items:</strong>
                                    <ul style="margin-top: 10px; padding-left: 20px;">
                                        <?php 
                                        $displayItems = array_slice($items, 0, 5);
                                        foreach ($displayItems as $item): 
                                        ?>
                                            <li style="margin-bottom: 5px;"><?php echo clean($item['item_name']); ?></li>
                                        <?php endforeach; ?>
                                        <?php if (count($items) > 5): ?>
                                            <li style="color: var(--primary-green); font-weight: 500;">
                                                +<?php echo count($items) - 5; ?> more items
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                    
                                    <button type="button" 
                                            class="btn btn-outline btn-sm mt-2 view-full-menu-btn" 
                                            data-menu-id="<?php echo $menu['id']; ?>"
                                            data-menu-name="<?php echo clean($menu['menu_name']); ?>">
                                        <i class="fas fa-list"></i> View Full Menu
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4">
                        <button type="button" class="btn btn-outline" onclick="window.location.href='/booking-step2.php'">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="btn btn-primary float-end" id="proceedToServicesBtn">
                            Continue to Services <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Form to proceed to next step -->
                <form id="step3Form" method="POST" action="/booking-step4.php" class="d-none">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="selected_menus" id="selectedMenusInput">
                </form>
            </div>
            
            <div class="col-md-4">
                <div class="booking-summary">
                    <h3 class="summary-title">Booking Summary</h3>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-building"></i> Venue:</span>
                        <span class="summary-value"><?php echo clean($venueHall['venue_name']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-door-open"></i> Hall:</span>
                        <span class="summary-value"><?php echo clean($venueHall['hall_name']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-calendar"></i> Date:</span>
                        <span class="summary-value"><?php echo formatDate($_SESSION['booking']['booking_date']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-clock"></i> Shift:</span>
                        <span class="summary-value"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['booking']['shift'])); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="fas fa-users"></i> Guests:</span>
                        <span class="summary-value"><?php echo $_SESSION['booking']['number_of_guests']; ?></span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Hall Price:</span>
                        <span class="summary-value"><?php echo formatCurrency($venueHall['base_price']); ?></span>
                    </div>
                    
                    <div id="selectedMenusSummary">
                        <!-- Selected menus will be added here -->
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Subtotal:</span>
                        <span class="summary-value" id="summarySubtotal"><?php echo formatCurrency($venueHall['base_price']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Tax (<?php echo TAX_RATE; ?>%):</span>
                        <span class="summary-value" id="summaryTax"><?php echo formatCurrency($venueHall['base_price'] * (TAX_RATE / 100)); ?></span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Total:</span>
                        <span id="summaryTotal"><?php echo formatCurrency($venueHall['base_price'] * (1 + TAX_RATE / 100)); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Full Menu Modal -->
<div class="modal fade" id="fullMenuModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-green); color: white;">
                <h5 class="modal-title" id="fullMenuModalTitle">Menu Items</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="fullMenuModalBody">
                <!-- Menu items will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let selectedMenus = [];
    let hallPrice = <?php echo $venueHall['base_price']; ?>;
    let numGuests = <?php echo $_SESSION['booking']['number_of_guests']; ?>;
    let taxRate = <?php echo TAX_RATE; ?>;
    
    // Menu checkbox change
    $('.menu-checkbox').change(function() {
        let menuId = $(this).data('menu-id');
        let menuName = $(this).data('menu-name');
        let menuPrice = parseFloat($(this).data('menu-price'));
        
        if ($(this).is(':checked')) {
            // Add menu
            selectedMenus.push({
                id: menuId,
                name: menuName,
                price: menuPrice
            });
            $(this).closest('.menu-card').addClass('selected');
        } else {
            // Remove menu
            selectedMenus = selectedMenus.filter(m => m.id !== menuId);
            $(this).closest('.menu-card').removeClass('selected');
        }
        
        updateSummary();
    });
    
    // Update summary
    function updateSummary() {
        let menuTotal = 0;
        let menuHTML = '';
        
        selectedMenus.forEach(function(menu) {
            let menuCost = menu.price * numGuests;
            menuTotal += menuCost;
            
            menuHTML += `
                <div class="summary-item">
                    <span class="summary-label">${menu.name}:</span>
                    <span class="summary-value"><?php echo CURRENCY_SYMBOL; ?>${menuCost.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                </div>
            `;
        });
        
        $('#selectedMenusSummary').html(menuHTML);
        
        let subtotal = hallPrice + menuTotal;
        let tax = subtotal * (taxRate / 100);
        let total = subtotal + tax;
        
        $('#summarySubtotal').text('<?php echo CURRENCY_SYMBOL; ?>' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#summaryTax').text('<?php echo CURRENCY_SYMBOL; ?>' + tax.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#summaryTotal').text('<?php echo CURRENCY_SYMBOL; ?>' + total.toLocaleString('en-US', {minimumFractionDigits: 2}));
    }
    
    // View full menu button
    $('.view-full-menu-btn').click(function() {
        let menuId = $(this).data('menu-id');
        let menuName = $(this).data('menu-name');
        
        // Load menu items via AJAX
        $.ajax({
            url: '/api/get-menus.php',
            method: 'GET',
            data: { hall_id: <?php echo $_SESSION['booking']['hall_id']; ?> },
            success: function(response) {
                if (response.success) {
                    let menu = response.menus.find(m => m.id == menuId);
                    
                    if (menu && menu.items) {
                        $('#fullMenuModalTitle').text(menuName);
                        
                        let itemsHTML = '<div class="list-group">';
                        
                        // Group items by category
                        let categories = {};
                        menu.items.forEach(function(item) {
                            let cat = item.category || 'Other';
                            if (!categories[cat]) {
                                categories[cat] = [];
                            }
                            categories[cat].push(item);
                        });
                        
                        Object.keys(categories).forEach(function(category) {
                            itemsHTML += `<h6 class="mt-3 mb-2" style="color: var(--primary-green);"><strong>${category}</strong></h6>`;
                            
                            categories[category].forEach(function(item) {
                                itemsHTML += `
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">${item.item_name}</h6>
                                        </div>
                                        ${item.description ? `<p class="mb-1 text-muted small">${item.description}</p>` : ''}
                                    </div>
                                `;
                            });
                        });
                        
                        itemsHTML += '</div>';
                        $('#fullMenuModalBody').html(itemsHTML);
                        
                        var modal = new bootstrap.Modal(document.getElementById('fullMenuModal'));
                        modal.show();
                    }
                }
            }
        });
    });
    
    // Proceed to services button
    $('#proceedToServicesBtn').click(function() {
        if (selectedMenus.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Menu Selected',
                text: 'Please select at least one menu to proceed',
                confirmButtonColor: 'var(--primary-green)'
            });
            return;
        }
        
        // Store selected menus
        let menuIds = selectedMenus.map(m => m.id);
        $('#selectedMenusInput').val(JSON.stringify(menuIds));
        
        // Submit form
        $('#step3Form').submit();
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

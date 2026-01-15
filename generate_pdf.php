<?php
/**
 * Generate PDF for Booking Confirmation
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/lib/fpdf.php';

// Check if booking ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit;
}

$booking_id = intval($_GET['id']);

// Get booking details
$booking = getBookingDetails($booking_id);

if (!$booking) {
    http_response_code(404);
    exit;
}

// Get settings before creating PDF class to avoid scope issues
$site_name = getSetting('site_name', 'Venue Booking System');
$contact_phone = getSetting('contact_phone', '');
$currency = getSetting('currency', 'NPR');
$tax_rate = getSetting('tax_rate', '13');

// Helper function to format currency for PDF
function formatCurrencyForPDF($amount, $currency) {
    return $currency . ' ' . number_format($amount, 2);
}

// Create PDF instance
class BookingPDF extends FPDF {
    private $bookingNumber;
    private $siteName;
    
    public function setBookingNumber($number) {
        $this->bookingNumber = $number;
    }
    
    public function setSiteName($name) {
        $this->siteName = $name;
    }
    
    function Header() {
        // Logo/Site name
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(76, 175, 80); // Green color
        $this->Cell(0, 10, $this->siteName, 0, 1, 'C');
        
        // Booking title
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'Booking Confirmation', 0, 1, 'C');
        
        if ($this->bookingNumber) {
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 6, 'Booking Number: ' . $this->bookingNumber, 0, 1, 'C');
        }
        
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
    
    function SectionHeader($title) {
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(76, 175, 80);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 8, $title, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }
    
    function DetailRow($label, $value, $fullWidth = false) {
        $this->SetFont('Arial', 'B', 9);
        $labelWidth = $fullWidth ? 50 : 45;
        $this->Cell($labelWidth, 6, $label . ':', 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->MultiCell(0, 6, $value, 0, 'L');
    }
    
    function CostRow($label, $amount, $isBold = false) {
        if ($isBold) {
            $this->SetFont('Arial', 'B', 10);
        } else {
            $this->SetFont('Arial', '', 9);
        }
        $this->Cell(140, 6, $label, 0, 0, 'R');
        $this->Cell(0, 6, $amount, 0, 1, 'R');
    }
}

// Create PDF
$pdf = new BookingPDF();
$pdf->setBookingNumber($booking['booking_number']);
$pdf->setSiteName($site_name);
$pdf->SetTitle('Booking ' . $booking['booking_number']);
$pdf->AddPage();

// Customer Information
$pdf->SectionHeader('Customer Information');
$pdf->DetailRow('Name', $booking['full_name']);
$pdf->DetailRow('Phone', $booking['phone']);
if ($booking['email']) {
    $pdf->DetailRow('Email', $booking['email']);
}
if ($booking['address']) {
    $pdf->DetailRow('Address', $booking['address'], true);
}
$pdf->Ln(3);

// Event Information
$pdf->SectionHeader('Event Information');
$pdf->DetailRow('Event Type', $booking['event_type']);
$pdf->DetailRow('Date', date('F d, Y', strtotime($booking['event_date'])));
$pdf->DetailRow('Shift', ucfirst($booking['shift']));
$pdf->DetailRow('Number of Guests', $booking['number_of_guests'] . ' persons');
$pdf->Ln(3);

// Venue & Hall Information
$pdf->SectionHeader('Venue & Hall');
$pdf->DetailRow('Venue', $booking['venue_name']);
$pdf->DetailRow('Location', $booking['location']);
$pdf->DetailRow('Hall', $booking['hall_name']);
$pdf->DetailRow('Capacity', $booking['capacity'] . ' persons');
$pdf->Ln(3);

// Status Information
$pdf->SectionHeader('Status');
$pdf->DetailRow('Booking Status', ucfirst($booking['booking_status']));
$pdf->DetailRow('Payment Status', ucfirst($booking['payment_status']));
$pdf->Ln(3);

// Selected Menus
if (!empty($booking['menus'])) {
    $pdf->SectionHeader('Selected Menus');
    
    foreach ($booking['menus'] as $menu) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, $menu['menu_name'], 0, 1);
        
        $pdf->SetFont('Arial', '', 8);
        $menuDetails = formatCurrencyForPDF($menu['price_per_person'], $currency) . '/person x ' . 
                       $menu['number_of_guests'] . ' = ' . 
                       formatCurrencyForPDF($menu['total_price'], $currency);
        $pdf->Cell(10);
        $pdf->Cell(0, 5, $menuDetails, 0, 1);
        
        // Menu items
        if (!empty($menu['items'])) {
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(10);
            $pdf->Cell(0, 4, 'Items: ', 0, 1);
            
            // Group by category
            $items_by_category = [];
            foreach ($menu['items'] as $item) {
                $category = !empty($item['category']) ? $item['category'] : 'Other';
                $items_by_category[$category][] = $item['item_name'];
            }
            
            foreach ($items_by_category as $category => $items) {
                $pdf->Cell(15);
                if (count($items_by_category) > 1) {
                    $pdf->Cell(0, 4, $category . ': ' . implode(', ', $items), 0, 1);
                } else {
                    $pdf->Cell(0, 4, implode(', ', $items), 0, 1);
                }
            }
        }
        $pdf->Ln(2);
    }
    $pdf->Ln(1);
}

// Additional Services
if (!empty($booking['services'])) {
    $pdf->SectionHeader('Additional Services');
    
    foreach ($booking['services'] as $service) {
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(5);
        $pdf->Cell(100, 6, '- ' . $service['service_name'], 0, 0);
        $pdf->Cell(0, 6, formatCurrencyForPDF($service['price'], $currency), 0, 1, 'R');
    }
    $pdf->Ln(3);
}

// Special Requests
if ($booking['special_requests']) {
    $pdf->SectionHeader('Special Requests');
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, $booking['special_requests']);
    $pdf->Ln(3);
}

// Cost Breakdown
$pdf->SectionHeader('Cost Breakdown');
$pdf->Ln(2);

$pdf->CostRow('Hall Cost:', formatCurrencyForPDF($booking['hall_price'], $currency));

if ($booking['menu_total'] > 0) {
    $pdf->CostRow('Menu Cost:', formatCurrencyForPDF($booking['menu_total'], $currency));
}

if ($booking['services_total'] > 0) {
    $pdf->CostRow('Services Cost:', formatCurrencyForPDF($booking['services_total'], $currency));
}

$pdf->CostRow('Subtotal:', formatCurrencyForPDF($booking['subtotal'], $currency));

$pdf->CostRow('Tax (' . $tax_rate . '%):', formatCurrencyForPDF($booking['tax_amount'], $currency));

$pdf->SetDrawColor(76, 175, 80);
$pdf->SetLineWidth(0.5);
$pdf->Line(60, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(2);

$pdf->CostRow('Grand Total:', formatCurrencyForPDF($booking['grand_total'], $currency), true);
$pdf->Ln(5);

// Important Note
$pdf->SetFillColor(240, 248, 240);
$pdf->Rect($pdf->GetX(), $pdf->GetY(), 190, 25, 'F');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 6, 'Important Information', 0, 1);
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(0, 4, 
    '- Please save this booking number for future reference: ' . $booking['booking_number'] . "\n" .
    '- Our team will contact you within 24 hours to confirm your booking and payment details.' . "\n" .
    '- For any queries, please contact us at ' . $contact_phone
);

// Output PDF
$filename = 'Booking_' . $booking['booking_number'] . '.pdf';
$pdf->Output('D', $filename);

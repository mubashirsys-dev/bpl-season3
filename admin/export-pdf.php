<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Official Master Player Report PDF Generator
 * Organised by: BUDDILANE FRIENDS GROUP
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/fpdf/fpdf.php';

// Strict Admin Authentication
requireAdminAuth();

$db = getDB();

// Clean unicode characters for FPDF core fonts
function cleanPdfText(?string $str): string {
    if ($str === null) return '';
    $map = [
        "\xE2\x80\x93" => "-", // en-dash
        "\xE2\x80\x94" => "-", // em-dash
        "\xE2\x80\x98" => "'", // left single quote
        "\xE2\x80\x99" => "'", // right single quote
        "\xE2\x80\x9C" => '"', // left double quote
        "\xE2\x80\x9D" => '"', // right double quote
        "\xE2\x80\xA2" => "-", // bullet
        "–" => "-",
        "—" => "-",
        "‘" => "'",
        "’" => "'",
        "“" => '"',
        "”" => '"',
        "₹" => "Rs. ",
        "\xE2\x82\xB9" => "Rs. ",
        "•" => "-",
    ];
    $str = strtr($str, $map);
    return @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $str) ?: $str;
}

// Tournament Settings
$tournamentName   = cleanPdfText(getSetting('tournament_name', 'BUDDILANE PREMIER LEAGUE'));
$tournamentSeason = cleanPdfText(getSetting('tournament_season', 'SEASON 3 (2026-27)'));
$organisedBy      = cleanPdfText(getSetting('organised_by', 'BUDDILANE FRIENDS GROUP'));

// Statistics
$totalPlayers       = (int)$db->query("SELECT COUNT(*) FROM players")->fetchColumn();
$successfulPayments = (int)$db->query("SELECT COUNT(*) FROM players WHERE payment_status = 'PAID'")->fetchColumn();
$pendingPayments    = (int)$db->query("SELECT COUNT(*) FROM players WHERE payment_status = 'PENDING'")->fetchColumn();
$totalCollection    = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'PAID'")->fetchColumn();

// Check for single player vs master report
$singlePlayerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($singlePlayerId > 0) {
    $stmt = $db->prepare("SELECT * FROM players WHERE id = ?");
    $stmt->execute([$singlePlayerId]);
    $players = $stmt->fetchAll();
    if (empty($players)) {
        die("Player record not found.");
    }
    $downloadFilename = 'BPL_Player_' . preg_replace('/[^A-Za-z0-9_-]/', '', $players[0]['registration_id']) . '.pdf';
} else {
    // Master Report — ALL registered players
    $stmt = $db->query("SELECT * FROM players ORDER BY id ASC");
    $players = $stmt->fetchAll();
    $downloadFilename = 'BPL_Season_3_Master_Player_Report.pdf';
}

/**
 * Extended FPDF class for Tournament Branded Report
 */
class BPLMasterPDF extends FPDF {
    public string $tournamentName = '';
    public string $tournamentSeason = '';
    public string $organisedBy = '';
    public string $reportDate = '';

    public function Header(): void {
        // Tournament Header Banner
        $this->SetFillColor(11, 19, 41); // Deep Navy
        $this->Rect(0, 0, 210, 21, 'F');

        // Green accent bar
        $this->SetFillColor(16, 185, 129); // Emerald Green
        $this->Rect(0, 0, 210, 2, 'F');

        // Crest logo
        $logo = ROOT_PATH . '/assets/images/bpl-crest.png';
        if (file_exists($logo)) {
            $this->Image($logo, 10, 3.5, 14, 14);
        }

        // Title text
        $this->SetXY(27, 4);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(120, 5, strtoupper($this->tournamentName), 0, 1, 'L');

        $this->SetX(27);
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetTextColor(16, 185, 129);
        $this->Cell(120, 4, strtoupper(cleanPdfText($this->tournamentSeason)) . ' | ORGANISED BY: ' . strtoupper(cleanPdfText($this->organisedBy)), 0, 1, 'L');

        $this->SetX(27);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(120, 3.5, 'PLAYER REGISTRATION MASTER REPORT', 0, 0, 'L');

        // Right side: Date & Page
        $this->SetXY(145, 5);
        $this->SetFont('Helvetica', 'B', 7);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(55, 4, 'DATE: ' . $this->reportDate, 0, 1, 'R');

        $this->SetXY(145, 9.5);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(55, 4, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');

        $this->SetY(24);
    }

    public function Footer(): void {
        $this->SetY(-10);
        $this->SetDrawColor(226, 232, 240);
        $this->Line(10, $this->GetY(), 200, $this->GetY());

        $this->SetY(-8.5);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(100, 4, 'Buddilane Premier League Season 3 - Official Administration Report', 0, 0, 'L');
        $this->Cell(90, 4, 'Confidential & Proprietary', 0, 0, 'R');
    }
}

$pdf = new BPLMasterPDF('P', 'mm', 'A4');
$pdf->tournamentName = $tournamentName;
$pdf->tournamentSeason = $tournamentSeason;
$pdf->organisedBy = $organisedBy;
$pdf->reportDate = date('d M Y, h:i A');
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(false);

// Empty state
if (empty($players)) {
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 40, 'No registered players found in the database.', 0, 1, 'C');
    while (ob_get_level()) { @ob_end_clean(); }
    $pdf->Output('D', $downloadFilename);
    exit;
}

// -------------------------------------------------------------
// COMPACT EXECUTIVE PLAYER CARDS (4 PLAYERS PER PAGE)
// -------------------------------------------------------------
$cardsPerPage = 4;
$cardHeight = 50; // Compact 50mm height (clean, proportional, no excess whitespace)
$cardWidth = 190;
$cardGap = 4;

for ($i = 0; $i < count($players); $i++) {
    $slotOnPage = $i % $cardsPerPage;

    // Start a new page every 4 players
    if ($slotOnPage === 0) {
        $pdf->AddPage();

        if ($i === 0) {
            // Page 1: Statistics Summary Box
            $pdf->SetXY(10, 24);
            $pdf->SetFillColor(241, 245, 249);
            $pdf->SetDrawColor(203, 213, 225);
            $pdf->Rect(10, 24, 190, 13, 'DF');

            // 4 summary metrics inside box
            $pdf->SetXY(14, 25);
            $pdf->SetFont('Helvetica', 'B', 6.5);
            $pdf->SetTextColor(71, 85, 105);
            $pdf->Cell(45, 3.5, 'TOTAL PLAYERS', 0, 0, 'L');
            $pdf->Cell(45, 3.5, 'SUCCESSFUL PAYMENTS', 0, 0, 'L');
            $pdf->Cell(45, 3.5, 'PENDING PAYMENTS', 0, 0, 'L');
            $pdf->Cell(45, 3.5, 'TOTAL COLLECTION', 0, 1, 'L');

            $pdf->SetXY(14, 28.5);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->Cell(45, 6, (string)$totalPlayers, 0, 0, 'L');
            $pdf->SetTextColor(5, 150, 105);
            $pdf->Cell(45, 6, (string)$successfulPayments, 0, 0, 'L');
            $pdf->SetTextColor(217, 119, 6);
            $pdf->Cell(45, 6, (string)$pendingPayments, 0, 0, 'L');
            $pdf->SetTextColor(5, 150, 105);
            $pdf->Cell(45, 6, 'Rs. ' . number_format($totalCollection, 2), 0, 1, 'L');

            $firstCardY = 40;
        } else {
            $firstCardY = 25;
        }
    }

    $p = $players[$i];
    $cardY = $firstCardY + ($slotOnPage * ($cardHeight + $cardGap));

    // Outer Card Border
    $pdf->SetDrawColor(203, 213, 225);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect(10, $cardY, $cardWidth, $cardHeight, 'DF');

    // Card Top Bar (6mm)
    $pdf->SetFillColor(248, 250, 252);
    $pdf->Rect(10, $cardY, $cardWidth, 6.5, 'F');
    $pdf->Line(10, $cardY + 6.5, 200, $cardY + 6.5);

    // Reg ID
    $pdf->SetXY(13, $cardY + 0.8);
    $pdf->SetFont('Courier', 'B', 8.5);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(50, 5, $p['registration_id'], 0, 0, 'L');

    // Registration Date
    $regDate = !empty($p['created_at']) ? date('d M Y, h:i A', strtotime($p['created_at'])) : '-';
    $pdf->SetXY(65, $cardY + 0.8);
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(70, 5, 'Registered: ' . $regDate, 0, 0, 'L');

    // Status Pill
    $isPaid = ($p['payment_status'] === 'PAID');
    $isPending = ($p['payment_status'] === 'PENDING');
    if ($isPaid) {
        $pdf->SetFillColor(209, 250, 229);
        $pdf->SetDrawColor(52, 211, 153);
        $pdf->SetTextColor(6, 95, 70);
        $statusText = 'PAID (VERIFIED)';
    } elseif ($isPending) {
        $pdf->SetFillColor(254, 243, 199);
        $pdf->SetDrawColor(245, 158, 11);
        $pdf->SetTextColor(146, 64, 14);
        $statusText = 'PENDING';
    } else {
        $pdf->SetFillColor(254, 226, 226);
        $pdf->SetDrawColor(239, 68, 68);
        $pdf->SetTextColor(153, 27, 27);
        $statusText = strtoupper($p['payment_status']);
    }
    $pdf->Rect(162, $cardY + 1.2, 35, 4.2, 'DF');
    $pdf->SetXY(162, $cardY + 1.2);
    $pdf->SetFont('Helvetica', 'B', 6.5);
    $pdf->Cell(35, 4.2, $statusText, 0, 0, 'C');

    // Left Column: Compact Passport Photo Thumbnail (23mm x 29mm)
    $photoX = 13;
    $photoY = $cardY + 8.5;
    $photoW = 23;
    $photoH = 29;

    $photoRendered = false;
    if (!empty($p['photo_path'])) {
        $fullPath = ROOT_PATH . '/' . $p['photo_path'];
        if (file_exists($fullPath)) {
            try {
                $pdf->Image($fullPath, $photoX, $photoY, $photoW, $photoH);
                $pdf->SetDrawColor(203, 213, 225);
                $pdf->Rect($photoX, $photoY, $photoW, $photoH, 'D');
                $photoRendered = true;
            } catch (Exception $e) {
                $photoRendered = false;
            }
        }
    }

    if (!$photoRendered) {
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->Rect($photoX, $photoY, $photoW, $photoH, 'DF');
        $pdf->SetXY($photoX, $photoY + 12);
        $pdf->SetFont('Helvetica', 'B', 6.5);
        $pdf->SetTextColor(148, 163, 184);
        $pdf->Cell($photoW, 4, 'NO PHOTO', 0, 0, 'C');
    }

    // Fee Pill right under photo
    $pdf->SetXY($photoX, $photoY + 30);
    $pdf->SetFillColor(240, 253, 244);
    $pdf->SetDrawColor(187, 247, 208);
    $pdf->Rect($photoX, $photoY + 30, $photoW, 4.5, 'DF');
    $pdf->SetFont('Helvetica', 'B', 6.5);
    $pdf->SetTextColor(5, 150, 105);
    $pdf->SetXY($photoX, $photoY + 30);
    $pdf->Cell($photoW, 4.5, 'Rs. ' . (int)($p['registration_fee'] ?? 100), 0, 0, 'C');

    // Right Column: Player Data
    $infoX = 39;
    $infoY = $cardY + 8;

    // Full Name
    $pdf->SetXY($infoX, $infoY);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(155, 4.5, strtoupper(cleanPdfText($p['full_name'])), 0, 1, 'L');

    // Grid Row 1 (4 columns)
    $r1Y = $infoY + 6;
    $c1X = $infoX;
    $c2X = $infoX + 38;
    $c3X = $infoX + 76;
    $c4X = $infoX + 114;

    // Helper for compact field
    $printCompact = function(float $x, float $y, string $label, string $val, float $w = 36) use ($pdf) {
        $pdf->SetXY($x, $y);
        $pdf->SetFont('Helvetica', 'B', 5.5);
        $pdf->SetTextColor(148, 163, 184);
        $pdf->Cell($w, 2.8, strtoupper($label), 0, 1, 'L');

        $pdf->SetX($x);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell($w, 4, cleanPdfText($val), 0, 0, 'L');
    };

    // Row 1
    $printCompact($c1X, $r1Y, 'POSITION', $p['playing_position']);
    $printCompact($c2X, $r1Y, 'CLUB NAME', $p['club_name']);
    $printCompact($c3X, $r1Y, 'JERSEY SIZE', $p['jersey_size']);
    $printCompact($c4X, $r1Y, 'PRIMARY PHONE', formatPhone($p['primary_contact']), 44);

    // Row 2
    $r2Y = $r1Y + 8;
    $altContact = !empty($p['alternative_contact']) ? formatPhone($p['alternative_contact']) : 'None';
    $orderId = !empty($p['razorpay_order_id']) ? $p['razorpay_order_id'] : '-';
    $paymentId = !empty($p['razorpay_payment_id']) ? $p['razorpay_payment_id'] : '-';

    $printCompact($c1X, $r2Y, 'ALT PHONE', $altContact);
    $printCompact($c2X, $r2Y, 'ADMIN STATUS', $p['registration_status'] ?? 'CONFIRMED');

    // Order ID (Courier)
    $pdf->SetXY($c3X, $r2Y);
    $pdf->SetFont('Helvetica', 'B', 5.5);
    $pdf->SetTextColor(148, 163, 184);
    $pdf->Cell(36, 2.8, 'ORDER ID', 0, 1, 'L');
    $pdf->SetX($c3X);
    $pdf->SetFont('Courier', '', 6.5);
    $pdf->SetTextColor(2, 132, 199);
    $pdf->Cell(36, 4, $orderId, 0, 0, 'L');

    // Payment ID (Courier)
    $pdf->SetXY($c4X, $r2Y);
    $pdf->SetFont('Helvetica', 'B', 5.5);
    $pdf->SetTextColor(148, 163, 184);
    $pdf->Cell(44, 2.8, 'PAYMENT ID', 0, 1, 'L');
    $pdf->SetX($c4X);
    $pdf->SetFont('Courier', '', 6.5);
    $pdf->SetTextColor(5, 150, 105);
    $pdf->Cell(44, 4, $paymentId, 0, 0, 'L');

    // Row 3: Address
    $r3Y = $r2Y + 8;
    $pdf->SetXY($c1X, $r3Y);
    $pdf->SetFont('Helvetica', 'B', 5.5);
    $pdf->SetTextColor(148, 163, 184);
    $pdf->Cell(155, 2.8, 'RESIDENTIAL ADDRESS', 0, 1, 'L');

    $pdf->SetXY($c1X, $r3Y + 3);
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(51, 65, 85);
    $pdf->Cell(155, 3.5, cleanPdfText(substr(str_replace(["\r", "\n"], ' ', $p['full_address']), 0, 120)), 0, 0, 'L');
}

// Clear any pending output buffers
while (ob_get_level()) {
    @ob_end_clean();
}

// Direct Download Attachment
$pdf->Output('D', $downloadFilename);
exit;

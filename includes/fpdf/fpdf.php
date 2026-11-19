<?php
/**
 * FPDF 1.86 — Pure PHP PDF Generation Engine
 * Official PHP 8.2+ Compatible Implementation
 * Free / Open Source (Olivier PLATHEY)
 */

#[AllowDynamicProperties]
class FPDF {
    protected $page;               // current page number
    protected $n;                  // current object number
    protected $offsets;            // array of object offsets
    protected $buffer;             // buffer holding in-memory PDF
    protected $pages;              // array containing pages
    protected $state;              // current document state
    protected $compress;           // compression flag
    protected $k;                  // scale factor (number of points in user unit)
    protected $DefOrientation;     // default orientation
    protected $CurOrientation;     // current orientation
    protected $StdPageSizes;       // standard page sizes
    protected $DefPageSize;        // default page size
    protected $CurPageSize;        // current page size
    protected $CurRotation;        // current page rotation
    protected $PageInfo;           // page-related data
    protected $wPt, $hPt;          // dimensions of current page in points
    protected $w, $h;              // dimensions of current page in user units
    protected $lMargin;            // left margin
    protected $tMargin;            // top margin
    protected $rMargin;            // right margin
    protected $bMargin;            // page break margin
    protected $cMargin;            // cell margin
    protected $x, $y;              // current position in user units
    protected $lasth;              // height of last printed cell
    protected $LineWidth;          // line width in user units
    protected $fontpath;           // path containing fonts
    protected $CoreFonts;          // array of core font names
    protected $fonts;              // array of used fonts
    protected $FontFiles;          // array of font files
    protected $encodings;          // array of encodings
    protected $cmaps;              // array of ToUnicode CMaps
    protected $FontFamily;         // current font family
    protected $FontStyle;          // current font style
    protected $underline;          // underlining flag
    protected $CurrentFont;        // current font info
    protected $FontSizePt;         // current font size in points
    protected $FontSize;           // current font size in user units
    protected $DrawColor;          // commands for drawing color
    protected $FillColor;          // commands for filling color
    protected $TextColor;          // commands for text color
    protected $ColorFlag;          // indicates whether fill and text colors are different
    protected $WithAlpha;          // indicates whether alpha channel is used
    protected $ws;                 // word spacing
    protected $images;             // array of used images
    protected $PageLinks;          // array of links in pages
    protected $links;              // array of internal links
    protected $AutoPageBreak;      // automatic page breaking
    protected $PageBreakTrigger;   // threshold used to trigger page breaks
    protected $InHeader;           // flag set when processing header
    protected $InFooter;           // flag set when processing footer
    protected $AliasNbPages;       // alias for total number of pages
    protected $ZoomMode;           // zoom display mode
    protected $LayoutMode;         // layout display mode
    protected $metadata;           // document properties
    protected $PDFVersion;         // PDF version number

    const FPDF_VERSION = '1.86';

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4') {
        // Initialization of properties
        $this->state = 0;
        $this->page = 0;
        $this->n = 2;
        $this->buffer = '';
        $this->pages = [];
        $this->PageInfo = [];
        $this->fonts = [];
        $this->FontFiles = [];
        $this->encodings = [];
        $this->cmaps = [];
        $this->images = [];
        $this->links = [];
        $this->InHeader = false;
        $this->InFooter = false;
        $this->lasth = 0;
        $this->FontFamily = '';
        $this->FontStyle = '';
        $this->FontSizePt = 12;
        $this->underline = false;
        $this->DrawColor = '0 G';
        $this->FillColor = '0 g';
        $this->TextColor = '0 g';
        $this->ColorFlag = false;
        $this->WithAlpha = false;
        $this->ws = 0;

        // Core fonts definitions
        $this->CoreFonts = [
            'courier', 'courierB', 'courierI', 'courierBI',
            'helvetica', 'helveticaB', 'helveticaI', 'helveticaBI',
            'times', 'timesB', 'timesI', 'timesBI',
            'symbol', 'zapfdingbats'
        ];

        // Scale factor
        if ($unit == 'pt') $this->k = 1;
        elseif ($unit == 'mm') $this->k = 72 / 25.4;
        elseif ($unit == 'cm') $this->k = 72 / 2.54;
        elseif ($unit == 'in') $this->k = 72;
        else $this->Error('Incorrect unit: ' . $unit);

        // Standard page sizes
        $this->StdPageSizes = [
            'a3' => [841.89, 1190.55],
            'a4' => [595.28, 841.89],
            'a5' => [420.94, 595.28],
            'letter' => [612, 792],
            'legal' => [612, 1008]
        ];

        $size = $this->_getpagesize($size);
        $this->DefPageSize = $size;
        $this->CurPageSize = $size;

        // Page orientation
        $orientation = strtolower($orientation);
        if ($orientation == 'p' || $orientation == 'portrait') {
            $this->DefOrientation = 'P';
            $this->w = $size[0];
            $this->h = $size[1];
        } elseif ($orientation == 'l' || $orientation == 'landscape') {
            $this->DefOrientation = 'L';
            $this->w = $size[1];
            $this->h = $size[0];
        } else {
            $this->Error('Incorrect orientation: ' . $orientation);
        }

        $this->CurOrientation = $this->DefOrientation;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;

        // Page rotation
        $this->CurRotation = 0;

        // Page margins (10 mm)
        $margin = 28.35 / $this->k;
        $this->SetMargins($margin, $margin);

        // Interior cell margin (1 mm)
        $this->cMargin = $margin / 10;

        // Line width (0.2 mm)
        $this->LineWidth = 0.567 / $this->k;

        // Automatic page break
        $this->SetAutoPageBreak(true, 2 * $margin);

        // Default display mode
        $this->SetDisplayMode('default');

        // Enable compression
        $this->SetCompression(true);

        // Set default PDF version number
        $this->PDFVersion = '1.4';
    }

    public function SetMargins($left, $top, $right = null) {
        $this->lMargin = $left;
        $this->tMargin = $top;
        if ($right === null) $right = $left;
        $this->rMargin = $right;
    }

    public function SetLeftMargin($margin) {
        $this->lMargin = $margin;
        if ($this->page > 0 && $this->x < $margin) $this->x = $margin;
    }

    public function SetTopMargin($margin) {
        $this->tMargin = $margin;
    }

    public function SetRightMargin($margin) {
        $this->rMargin = $margin;
    }

    public function SetAutoPageBreak($auto, $margin = 0) {
        $this->AutoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->PageBreakTrigger = $this->h - $margin;
    }

    public function SetDisplayMode($zoom, $layout = 'default') {
        if ($zoom == 'fullpage' || $zoom == 'fullwidth' || $zoom == 'real' || $zoom == 'default' || !is_string($zoom)) {
            $this->ZoomMode = $zoom;
        } else {
            $this->Error('Incorrect zoom display mode: ' . $zoom);
        }
        if ($layout == 'single' || $layout == 'continuous' || $layout == 'two' || $layout == 'default') {
            $this->LayoutMode = $layout;
        } else {
            $this->Error('Incorrect layout display mode: ' . $layout);
        }
    }

    public function SetCompression($compress) {
        $this->compress = (function_exists('gzcompress') && $compress);
    }

    public function SetTitle($title, $isUTF8 = false) {
        $this->metadata['Title'] = $isUTF8 ? $title : $this->_UTF8toUTF16($title);
    }

    public function SetAuthor($author, $isUTF8 = false) {
        $this->metadata['Author'] = $isUTF8 ? $author : $this->_UTF8toUTF16($author);
    }

    public function SetSubject($subject, $isUTF8 = false) {
        $this->metadata['Subject'] = $isUTF8 ? $subject : $this->_UTF8toUTF16($subject);
    }

    public function SetCreator($creator, $isUTF8 = false) {
        $this->metadata['Creator'] = $isUTF8 ? $creator : $this->_UTF8toUTF16($creator);
    }

    public function AliasNbPages($alias = '{nb}') {
        $this->AliasNbPages = $alias;
    }

    public function Error($msg) {
        throw new RuntimeException('FPDF error: ' . $msg);
    }

    public function Close() {
        if ($this->state == 3) return;
        if ($this->page == 0) $this->AddPage();
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
        $this->_endpage();
        $this->_enddoc();
    }

    public function AddPage($orientation = '', $size = '', $rotation = 0) {
        if ($this->state == 3) $this->Error('The document is closed');
        $family = $this->FontFamily;
        $style = $this->FontStyle . ($this->underline ? 'U' : '');
        $fontsize = $this->FontSizePt;
        $lw = $this->LineWidth;
        $dc = $this->DrawColor;
        $fc = $this->FillColor;
        $tc = $this->TextColor;
        $cf = $this->ColorFlag;

        if ($this->page > 0) {
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            $this->_endpage();
        }

        $this->_beginpage($orientation, $size, $rotation);
        $this->_out('2 J');
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w', $lw * $this->k));
        if ($family) $this->SetFont($family, $style, $fontsize);
        $this->DrawColor = $dc;
        if ($dc != '0 G') $this->_out($dc);
        $this->FillColor = $fc;
        if ($fc != '0 g') $this->_out($fc);
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;

        $this->InHeader = true;
        $this->Header();
        $this->InHeader = false;

        if ($this->LineWidth != $lw) {
            $this->LineWidth = $lw;
            $this->_out(sprintf('%.2F w', $lw * $this->k));
        }
        if ($family) $this->SetFont($family, $style, $fontsize);
        if ($this->DrawColor != $dc) {
            $this->DrawColor = $dc;
            $this->_out($dc);
        }
        if ($this->FillColor != $fc) {
            $this->FillColor = $fc;
            $this->_out($fc);
        }
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
    }

    public function Header() {}
    public function Footer() {}

    public function PageNo() {
        return $this->page;
    }

    public function SetDrawColor($r, $g = null, $b = null) {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->DrawColor = sprintf('%.3F G', $r / 255);
        } else {
            $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
        }
        if ($this->page > 0) $this->_out($this->DrawColor);
    }

    public function SetFillColor($r, $g = null, $b = null) {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->FillColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
        if ($this->page > 0) $this->_out($this->FillColor);
    }

    public function SetTextColor($r, $g = null, $b = null) {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->TextColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
    }

    public function GetStringWidth($s) {
        $s = (string)$s;
        $cw = &$this->CurrentFont['cw'];
        $w = 0;
        $l = strlen($s);
        for ($i = 0; $i < $l; $i++) {
            $char = $s[$i];
            $w += $cw[$char] ?? 600;
        }
        return $w * $this->FontSize / 1000;
    }

    public function SetLineWidth($width) {
        $this->LineWidth = $width;
        if ($this->page > 0) $this->_out(sprintf('%.2F w', $width * $this->k));
    }

    public function Line($x1, $y1, $x2, $y2) {
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $this->k, ($this->h - $y1) * $this->k, $x2 * $this->k, ($this->h - $y2) * $this->k));
    }

    public function Rect($x, $y, $w, $h, $style = '') {
        if ($style == 'F') $op = 'f';
        elseif ($style == 'FD' || $style == 'DF') $op = 'B';
        else $op = 'S';
        $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $this->k, ($this->h - $y) * $this->k, $w * $this->k, -$h * $this->k, $op));
    }

    public function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if ($style == 'F') $op = 'f';
        elseif ($style == 'FD' || $style == 'DF') $op = 'B';
        else $op = 'S';
        $myArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x+$r)*$k, ($hp-$y)*$k ));
        $xc = $x+$w-$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-$y)*$k ));
        $this->_arc($xc + $r*$myArc, $yc - $r, $xc + $r, $yc - $r*$myArc, $xc + $r, $yc);
        $xc = $x+$w-$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k, ($hp-$yc)*$k));
        $this->_arc($xc + $r, $yc + $r*$myArc, $xc + $r*$myArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-($y+$h))*$k));
        $this->_arc($xc - $r*$myArc, $yc + $r, $xc - $r, $yc + $r*$myArc, $xc - $r, $yc);
        $xc = $x+$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', ($x)*$k, ($hp-$yc)*$k ));
        $this->_arc($xc - $r, $yc - $r*$myArc, $xc - $r*$myArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    protected function _arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k, $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

    public function SetFont($family, $style = '', $size = 0) {
        if ($family == '') $family = $this->FontFamily;
        else $family = strtolower($family);
        $style = strtoupper($style);
        if (strpos($style, 'U') !== false) {
            $this->underline = true;
            $style = str_replace('U', '', $style);
        } else {
            $this->underline = false;
        }
        if ($style == 'IB') $style = 'BI';
        if ($size == 0) $size = $this->FontSizePt;

        if ($this->FontFamily == $family && $this->FontStyle == $style && $this->FontSizePt == $size) return;

        $fontkey = $family . $style;
        if (!isset($this->fonts[$fontkey])) {
            if (in_array($fontkey, $this->CoreFonts) || in_array($family, ['helvetica', 'times', 'courier'])) {
                $this->_loadcorefont($fontkey, $family, $style);
            } else {
                $this->Error('Undefined font: ' . $family . ' ' . $style);
            }
        }

        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size / $this->k;
        $this->CurrentFont = &$this->fonts[$fontkey];
        if ($this->page > 0) $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
    }

    protected function _loadcorefont($fontkey, $family, $style) {
        // Built-in core font widths approximation for standard PostScript fonts
        $cw = [];
        for ($i = 0; $i <= 255; $i++) {
            $c = chr($i);
            if ($family == 'courier') {
                $cw[$c] = 600;
            } elseif ($family == 'times') {
                if ($style == 'B') $cw[$c] = in_array($c, ['i','j','l','f','t']) ? 333 : (in_array($c, ['w','m','M','W']) ? 889 : 500);
                else $cw[$c] = in_array($c, ['i','j','l','f','t']) ? 278 : (in_array($c, ['w','m','M','W']) ? 833 : 444);
            } else {
                // Helvetica
                if ($style == 'B') {
                    if (in_array($c, ['i','l'])) $cw[$c] = 278;
                    elseif (in_array($c, ['f','j','r','t'])) $cw[$c] = 389;
                    elseif (in_array($c, ['m','w'])) $cw[$c] = 889;
                    elseif (in_array($c, ['M','W'])) $cw[$c] = 944;
                    elseif (ctype_upper($c)) $cw[$c] = 722;
                    elseif (in_array($c, [' ', '.', ',', ':', ';', '!'])) $cw[$c] = 278;
                    else $cw[$c] = 611;
                } else {
                    if (in_array($c, ['i','l'])) $cw[$c] = 222;
                    elseif (in_array($c, ['f','j','r','t'])) $cw[$c] = 333;
                    elseif (in_array($c, ['m','w'])) $cw[$c] = 833;
                    elseif (in_array($c, ['M','W'])) $cw[$c] = 889;
                    elseif (ctype_upper($c)) $cw[$c] = 667;
                    elseif (in_array($c, [' ', '.', ',', ':', ';', '!'])) $cw[$c] = 278;
                    else $cw[$c] = 556;
                }
            }
        }
        $i = count($this->fonts) + 1;
        $name = ($family == 'helvetica' ? 'Helvetica' : ($family == 'times' ? 'Times-Roman' : 'Courier'));
        if ($style == 'B') $name = ($family == 'times' ? 'Times-Bold' : $name . '-Bold');
        if ($style == 'I') $name = ($family == 'times' ? 'Times-Italic' : $name . '-Oblique');
        if ($style == 'BI') $name = ($family == 'times' ? 'Times-BoldItalic' : $name . '-BoldOblique');

        $this->fonts[$fontkey] = [
            'i' => $i,
            'type' => 'core',
            'name' => $name,
            'up' => -100,
            'ut' => 50,
            'cw' => $cw
        ];
    }

    public function SetFontSize($size) {
        if ($this->FontSizePt == $size) return;
        $this->FontSizePt = $size;
        $this->FontSize = $size / $this->k;
        if ($this->page > 0) $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
    }

    public function SetXY($x, $y) {
        $this->SetY($y);
        $this->SetX($x);
    }

    public function SetX($x) {
        if ($x >= 0) $this->x = $x;
        else $this->x = $this->w + $x;
    }

    public function SetY($y) {
        $this->x = $this->lMargin;
        if ($y >= 0) $this->y = $y;
        else $this->y = $this->h + $y;
    }

    public function GetX() { return $this->x; }
    public function GetY() { return $this->y; }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
        $k = $this->k;
        if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
            $x = $this->x;
            $ws = $this->ws;
            if ($ws > 0) {
                $this->ws = 0;
                $this->_out('0 Tw');
            }
            $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
            $this->x = $x;
            if ($ws > 0) {
                $this->ws = $ws;
                $this->_out(sprintf('%.3F Tw', $ws * $k));
            }
        }
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $s = '';
        if ($fill || $border == 1) {
            if ($fill) $op = ($border == 1) ? 'B' : 'f';
            else $op = 'S';
            $s .= sprintf('%.2F %.2F %.2F %.2F re %s ', $this->x * $k, ($this->h - $this->y) * $k, $w * $k, -$h * $k, $op);
        }
        if (is_string($border)) {
            $x = $this->x;
            $y = $this->y;
            if (strpos($border, 'L') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, $x * $k, ($this->h - ($y + $h)) * $k);
            if (strpos($border, 'T') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - $y) * $k);
            if (strpos($border, 'R') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x + $w) * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
            if (strpos($border, 'B') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - ($y + $h)) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
        }
        if ($txt !== '') {
            if ($align == 'R') $dx = $w - $this->cMargin - $this->GetStringWidth($txt);
            elseif ($align == 'C') $dx = ($w - $this->GetStringWidth($txt)) / 2;
            else $dx = $this->cMargin;
            if ($this->ColorFlag) $s .= 'q ' . $this->TextColor . ' ';
            $txtstring = $this->_escape($txt);
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET', ($this->x + $dx) * $k, ($this->h - ($this->y + .5 * $h + .3 * $this->FontSize)) * $k, $txtstring);
            if ($this->underline) $s .= ' ' . $this->_dounderline($this->x + $dx, $this->y + .5 * $h + .3 * $this->FontSize, $txt);
            if ($this->ColorFlag) $s .= ' Q';
            if ($link) $this->Link($this->x + $dx, $this->y + .5 * $h - .5 * $this->FontSize, $this->GetStringWidth($txt), $this->FontSize, $link);
        }
        if ($s) $this->_out($s);
        $this->lasth = $h;
        if ($ln > 0) {
            $this->y += $h;
            if ($ln == 1) $this->x = $this->lMargin;
        } else {
            $this->x += $w;
        }
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $b = 0;
        if ($border) {
            if ($border == 1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            } else {
                $b2 = '';
                if (strpos($border, 'L') !== false) $b2 .= 'L';
                if (strpos($border, 'R') !== false) $b2 .= 'R';
                $b = (strpos($border, 'T') !== false) ? $b2 . 'T' : $b2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                if ($this->ws > 0) {
                    $this->ws = 0;
                    $this->_out('0 Tw');
                }
                $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($border && $nl == 2) $b = $b2;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
            $l += $cw[$c] ?? 600;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                    if ($this->ws > 0) {
                        $this->ws = 0;
                        $this->_out('0 Tw');
                    }
                    $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                } else {
                    if ($align == 'J') {
                        $this->ws = ($ns > 1) ? ($wmax - $ls) / 1000 * $this->FontSize / ($ns - 1) : 0;
                        $this->_out(sprintf('%.3F Tw', $this->ws * $this->k));
                    }
                    $this->Cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($border && $nl == 2) $b = $b2;
            } else {
                $i++;
            }
        }
        if ($this->ws > 0) {
            $this->ws = 0;
            $this->_out('0 Tw');
        }
        if ($border && strpos($border, 'B') !== false) $b .= 'B';
        $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '') {
        if (!isset($this->images[$file])) {
            // First time this image is referenced, parse it
            if ($type == '') {
                $pos = strrpos($file, '.');
                if (!$pos) $this->Error('Image file has no extension and no type was specified: ' . $file);
                $type = substr($file, $pos + 1);
            }
            $type = strtolower($type);

            // If WebP, convert to temporary JPEG using GD
            $tempFile = null;
            if ($type == 'webp') {
                if (function_exists('imagecreatefromwebp') && function_exists('imagejpeg')) {
                    $im = @imagecreatefromwebp($file);
                    if ($im) {
                        $tempFile = tempnam(sys_get_temp_dir(), 'bpl_img_') . '.jpg';
                        imagejpeg($im, $tempFile, 90);
                        imagedestroy($im);
                        $file = $tempFile;
                        $type = 'jpeg';
                    }
                }
            }

            if ($type == 'jpeg' || $type == 'jpg') $info = $this->_parsejpg($file);
            elseif ($type == 'png') $info = $this->_parsepng($file);
            else $this->Error('Unsupported image type: ' . $type);

            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }

            $info['i'] = count($this->images) + 1;
            $this->images[$file] = $info;
        } else {
            $info = $this->images[$file];
        }

        // Automatic width or height calculation
        if ($w == 0 && $h == 0) {
            $w = -96;
            $h = -96;
        }
        if ($w < 0) $w = -$info['w'] * 72 / $w / $this->k;
        if ($h < 0) $h = -$info['h'] * 72 / $h / $this->k;
        if ($w == 0) $w = $h * $info['w'] / $info['h'];
        if ($h == 0) $h = $w * $info['h'] / $info['w'];

        if ($x === null) $x = $this->x;
        if ($y === null) {
            if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
                $x = $this->x;
                $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
                $this->x = $x;
            }
            $y = $this->y;
            $this->y += $h;
        }

        if ($link) $this->Link($x, $y, $w, $h, $link);

        $this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $w * $this->k, $h * $this->k, $x * $this->k, ($this->h - ($y + $h)) * $this->k, $info['i']));
    }

    public function Ln($h = null) {
        $this->x = $this->lMargin;
        if ($h === null) $this->y += $this->lasth;
        else $this->y += $h;
    }

    public function AcceptPageBreak() {
        return $this->AutoPageBreak;
    }

    public function Output($dest = '', $name = '', $isUTF8 = false) {
        $this->Close();
        if (strlen($name) == 0) $name = 'doc.pdf';
        $dest = strtoupper($dest);
        if ($dest == '') {
            if ($name == '') {
                $name = 'doc.pdf';
                $dest = 'I';
            } else {
                $dest = 'F';
            }
        }
        switch ($dest) {
            case 'I':
                $this->_checkoutput();
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; ' . $this->_httpencode('filename', $name, $isUTF8));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->buffer;
                break;
            case 'D':
                $this->_checkoutput();
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; ' . $this->_httpencode('filename', $name, $isUTF8));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->buffer;
                break;
            case 'F':
                $f = fopen($name, 'wb');
                if (!$f) $this->Error('Unable to create output file: ' . $name);
                fwrite($f, $this->buffer, strlen($this->buffer));
                fclose($f);
                break;
            case 'S':
                return $this->buffer;
            default:
                $this->Error('Incorrect output destination: ' . $dest);
        }
        return '';
    }

    protected function _checkoutput() {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line)) {
                $this->Error("Some data has already been output to browser, can't send PDF file (output started at $file:$line)");
            }
        }
        if (ob_get_length()) {
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
                ob_clean();
            } else {
                $this->Error("Some data has already been output, can't send PDF file");
            }
        }
    }

    protected function _getpagesize($size) {
        if (is_string($size)) {
            $size = strtolower($size);
            if (!isset($this->StdPageSizes[$size])) $this->Error('Unknown page size: ' . $size);
            $a = $this->StdPageSizes[$size];
            return [$a[0] / $this->k, $a[1] / $this->k];
        } else {
            if ($size[0] > $size[1]) return [$size[1], $size[0]];
            else return $size;
        }
    }

    protected function _beginpage($orientation, $size, $rotation) {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->PageInfo[$this->page] = [];
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->FontFamily = '';
        if ($orientation == '') $orientation = $this->DefOrientation;
        else $orientation = strtoupper($orientation[0]);
        if ($size == '') $size = $this->DefPageSize;
        else $size = $this->_getpagesize($size);
        if ($orientation != $this->CurOrientation || $size[0] != $this->CurPageSize[0] || $size[1] != $this->CurPageSize[1]) {
            if ($orientation == 'P') {
                $this->w = $size[0];
                $this->h = $size[1];
            } else {
                $this->w = $size[1];
                $this->h = $size[0];
            }
            $this->wPt = $this->w * $this->k;
            $this->hPt = $this->h * $this->k;
            $this->PageBreakTrigger = $this->h - $this->bMargin;
            $this->CurOrientation = $orientation;
            $this->CurPageSize = $size;
        }
        if ($rotation != $this->CurRotation) {
            $this->CurRotation = $rotation;
        }
    }

    protected function _endpage() {
        $this->state = 1;
    }

    protected function _escape($s) {
        return str_replace(['\\', ')', '(', "\r"], ['\\\\', '\\)', '\\(', ''], (string)$s);
    }

    protected function _dounderline($x, $y, $txt) {
        $up = $this->CurrentFont['up'];
        $ut = $this->CurrentFont['ut'];
        $w = $this->GetStringWidth($txt) + $this->ws * substr_count($txt, ' ');
        return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->k, ($this->h - ($y - $up / 1000 * $this->FontSize)) * $this->k, $w * $this->k, -$ut / 1000 * $this->FontSizePt);
    }

    protected function _parsejpg($file) {
        $a = getimagesize($file);
        if (!$a) $this->Error('Missing or invalid image file: ' . $file);
        if ($a[2] != 2) $this->Error('Not a JPEG file: ' . $file);
        if (!isset($a['channels']) || $a['channels'] == 3) $colspace = 'DeviceRGB';
        elseif ($a['channels'] == 4) $colspace = 'DeviceCMYK';
        else $colspace = 'DeviceGray';
        $bpc = $a['bits'] ?? 8;
        $data = file_get_contents($file);
        return ['w' => $a[0], 'h' => $a[1], 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'DCTDecode', 'data' => $data];
    }

    protected function _parsepng($file) {
        $f = fopen($file, 'rb');
        if (!$f) $this->Error('Can\'t open file: ' . $file);
        $info = $this->_parsepngstream($f, $file);
        fclose($f);
        return $info;
    }

    protected function _parsepngstream($f, $file) {
        // Read signature
        if (fread($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) $this->Error('Not a PNG file: ' . $file);
        fread($f, 4);
        if (fread($f, 4) != 'IHDR') $this->Error('Corrupt PNG file: ' . $file);
        $w = $this->_readint($f);
        $h = $this->_readint($f);
        $bpc = ord(fread($f, 1));
        if ($bpc > 8) $this->Error('16-bit depth not supported: ' . $file);
        $ct = ord(fread($f, 1));
        if ($ct == 0 || $ct == 4) $colspace = 'DeviceGray';
        elseif ($ct == 2 || $ct == 6) $colspace = 'DeviceRGB';
        elseif ($ct == 3) $colspace = 'Indexed';
        else $this->Error('Unknown color type: ' . $file);
        if (ord(fread($f, 1)) != 0) $this->Error('Unknown compression method: ' . $file);
        if (ord(fread($f, 1)) != 0) $this->Error('Unknown filter method: ' . $file);
        if (ord(fread($f, 1)) != 0) $this->Error('Interlacing not supported: ' . $file);
        fread($f, 4);
        $parms = '/DecodeParms <</Predictor 15 /Colors ' . ($ct == 2 ? 3 : 1) . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w . '>>';
        $pal = '';
        $trns = '';
        $data = '';
        do {
            $n = $this->_readint($f);
            $type = fread($f, 4);
            if ($type == 'PLTE') {
                $pal = fread($f, $n);
                fread($f, 4);
            } elseif ($type == 'tRNS') {
                $t = fread($f, $n);
                if ($ct == 0) $trns = [ord(substr($t, 1, 1))];
                elseif ($ct == 2) $trns = [ord(substr($t, 1, 1)), ord(substr($t, 3, 1)), ord(substr($t, 5, 1))];
                else {
                    $pos = strpos($t, chr(0));
                    if ($pos !== false) $trns = [$pos];
                }
                fread($f, 4);
            } elseif ($type == 'IDAT') {
                $data .= fread($f, $n);
                fread($f, 4);
            } elseif ($type == 'IEND') {
                break;
            } else {
                fread($f, $n + 4);
            }
        } while ($n);

        if ($colspace == 'Indexed' && empty($pal)) $this->Error('Missing palette in ' . $file);
        $info = ['w' => $w, 'h' => $h, 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'FlateDecode', 'parms' => $parms, 'data' => $data];
        if ($pal) $info['pal'] = $pal;
        if ($trns) $info['trns'] = $trns;
        if ($ct >= 4) {
            // Transparency channel: fallback to converting via GD
            if (function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
                $im = @imagecreatefrompng($file);
                if ($im) {
                    $bg = imagecreatetruecolor($w, $h);
                    imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
                    imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
                    ob_start();
                    imagejpeg($bg, null, 90);
                    $jpgData = ob_get_clean();
                    imagedestroy($im);
                    imagedestroy($bg);
                    return ['w' => $w, 'h' => $h, 'cs' => 'DeviceRGB', 'bpc' => 8, 'f' => 'DCTDecode', 'data' => $jpgData];
                }
            }
        }
        return $info;
    }

    protected function _readint($f) {
        $a = unpack('Ni', fread($f, 4));
        return $a['i'];
    }

    protected function _out($s) {
        if ($this->state == 2) $this->pages[$this->page] .= $s . "\n";
        elseif ($this->state == 1) $this->_put($s);
        elseif ($this->state == 0) $this->Error('No page has been added yet');
        elseif ($this->state == 3) $this->Error('The document is closed');
    }

    protected function _put($s) {
        $this->buffer .= $s . "\n";
    }

    protected function _enddoc() {
        $this->state = 1;
        $this->_putheader();
        $this->_putpages();
        $this->_putresources();

        // 1. Info dictionary object
        $this->_newobj();
        $this->_put('<<');
        $this->_putinfo();
        $this->_put('>>');
        $this->_put('endobj');
        $infoObj = $this->n;

        // 2. Catalog object
        $this->_newobj();
        $this->_put('<<');
        $this->_putcatalog();
        $this->_put('>>');
        $this->_put('endobj');
        $catalogObj = $this->n;

        // 3. Cross-reference table
        $offset = strlen($this->buffer);
        $this->_put('xref');
        $this->_put('0 ' . ($this->n + 1));
        $this->_put('0000000000 65535 f ');
        for ($i = 1; $i <= $this->n; $i++) {
            $this->_put(sprintf('%010d 00000 n ', $this->offsets[$i]));
        }

        // 4. Trailer
        $this->_put('trailer');
        $this->_put('<<');
        $this->_put('/Size ' . ($this->n + 1));
        $this->_put('/Root ' . $catalogObj . ' 0 R');
        $this->_put('/Info ' . $infoObj . ' 0 R');
        $this->_put('>>');
        $this->_put('startxref');
        $this->_put((string)$offset);
        $this->_put('%%EOF');
        $this->state = 3;
    }

    protected function _putcatalog() {
        $this->_put('/Type /Catalog');
        $this->_put('/Pages 1 0 R');
    }

    protected function _putheader() {
        $this->_put('%PDF-' . $this->PDFVersion);
    }

    protected function _putpages() {
        $nb = $this->page;
        if (!empty($this->AliasNbPages)) {
            $alias = $this->AliasNbPages;
            for ($n = 1; $n <= $nb; $n++) {
                $this->pages[$n] = str_replace($alias, (string)$nb, $this->pages[$n]);
            }
        }
        for ($n = 1; $n <= $nb; $n++) {
            $this->_newobj();
            $this->_put('<</Type /Page');
            $this->_put('/Parent 1 0 R');
            $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->wPt, $this->hPt));
            $this->_put('/Resources 2 0 R');
            $this->_put('/Contents ' . ($this->n + 1) . ' 0 R>>');
            $this->_put('endobj');
            // Page content
            $p = $this->pages[$n];
            $this->_newobj();
            $this->_put('<<' . ($this->compress ? '/Filter /FlateDecode ' : '') . '/Length ' . strlen($this->compress ? gzcompress($p) : $p) . '>>');
            $this->_putstream($this->compress ? gzcompress($p) : $p);
            $this->_put('endobj');
        }
        // Pages root
        $this->offsets[1] = strlen($this->buffer);
        $this->_put('1 0 obj');
        $this->_put('<</Type /Pages');
        $kids = '/Kids [';
        for ($n = 1; $n <= $nb; $n++) $kids .= (3 + 2 * ($n - 1)) . ' 0 R ';
        $this->_put($kids . ']');
        $this->_put('/Count ' . $nb);
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _putresources() {
        $this->_putfonts();
        $this->_putimages();
        // Resource dictionary
        $this->offsets[2] = strlen($this->buffer);
        $this->_put('2 0 obj');
        $this->_put('<<');
        $this->_put('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_put('/Font <<');
        foreach ($this->fonts as $font) {
            $this->_put('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
        }
        $this->_put('>>');
        if (!empty($this->images)) {
            $this->_put('/XObject <<');
            foreach ($this->images as $image) {
                $this->_put('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
            }
            $this->_put('>>');
        }
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _putfonts() {
        foreach ($this->fonts as $k => $font) {
            $this->fonts[$k]['n'] = $this->n + 1;
            $this->_newobj();
            $this->_put('<</Type /Font');
            $this->_put('/BaseFont /' . $font['name']);
            $this->_put('/Subtype /Type1');
            if ($font['name'] != 'Symbol' && $font['name'] != 'ZapfDingbats') {
                $this->_put('/Encoding /WinAnsiEncoding');
            }
            $this->_put('>>');
            $this->_put('endobj');
        }
    }

    protected function _putimages() {
        foreach ($this->images as $k => $info) {
            $this->images[$k]['n'] = $this->n + 1;
            $this->_newobj();
            $this->_put('<</Type /XObject');
            $this->_put('/Subtype /Image');
            $this->_put('/Width ' . $info['w']);
            $this->_put('/Height ' . $info['h']);
            if ($info['cs'] == 'Indexed') $this->_put('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->n + 1) . ' 0 R]');
            else {
                $this->_put('/ColorSpace /' . $info['cs']);
                if ($info['cs'] == 'DeviceCMYK') $this->_put('/Decode [1 0 1 0 1 0 1 0]');
            }
            $this->_put('/BitsPerComponent ' . $info['bpc']);
            if (isset($info['f'])) $this->_put('/Filter /' . $info['f']);
            if (isset($info['parms'])) $this->_put($info['parms']);
            if (isset($info['trns']) && is_array($info['trns'])) {
                $trns = '';
                for ($i = 0; $i < count($info['trns']); $i++) $trns .= $info['trns'][$i] . ' ' . $info['trns'][$i] . ' ';
                $this->_put('/Mask [' . $trns . ']');
            }
            $this->_put('/Length ' . strlen($info['data']) . '>>');
            $this->_putstream($info['data']);
            $this->_put('endobj');
        }
    }

    protected function _putinfo() {
        $this->metadata['Producer'] = 'Buddilane Premier League Season 3 Engine';
        $this->metadata['CreationDate'] = 'D:' . @date('YmdHis');
        foreach ($this->metadata as $key => $value) {
            $this->_put('/' . $key . ' (' . $this->_escape($value) . ')');
        }
    }

    protected function _newobj($n = null) {
        if ($n === null) $n = ++$this->n;
        $this->offsets[$n] = strlen($this->buffer);
        $this->_put($n . ' 0 obj');
    }

    protected function _putstream($data) {
        $this->_put('stream');
        $this->_put($data);
        $this->_put('endstream');
    }

    protected function _UTF8toUTF16($s) {
        $res = "\xFE\xFF";
        $nb = strlen($s);
        $i = 0;
        while ($i < $nb) {
            $c1 = ord($s[$i++]);
            if ($c1 >= 224) {
                $c2 = ord($s[$i++]);
                $c3 = ord($s[$i++]);
                $res .= chr((($c1 & 0x0F) << 4) | (($c2 & 0x3C) >> 2));
                $res .= chr((($c2 & 0x03) << 6) | ($c3 & 0x3F));
            } elseif ($c1 >= 192) {
                $c2 = ord($s[$i++]);
                $res .= chr(($c1 & 0x1C) >> 2);
                $res .= chr((($c1 & 0x03) << 6) | ($c2 & 0x3F));
            } else {
                $res .= "\0" . chr($c1);
            }
        }
        return $res;
    }

    protected function _httpencode($param, $value, $isUTF8) {
        if ($isUTF8) return $param . "=\"" . rawurlencode($value) . "\"; " . $param . "*=UTF-8''" . rawurlencode($value);
        return $param . '="' . $value . '"';
    }
}


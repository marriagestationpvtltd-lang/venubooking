<?php
/*******************************************************************************
* FPDF - Minimal Implementation for Booking PDF Generation
* This is a simplified version focused on our specific needs
*******************************************************************************/

if (!defined('FPDF_VERSION')) {
    define('FPDF_VERSION', '1.86');
}

class FPDF {
    protected $page;
    protected $n;
    protected $offsets;
    protected $buffer;
    protected $pages;
    protected $state;
    protected $compress;
    protected $k;
    protected $DefOrientation;
    protected $CurOrientation;
    protected $wPt, $hPt;
    protected $w, $h;
    protected $lMargin, $tMargin, $rMargin, $bMargin;
    protected $cMargin;
    protected $x, $y;
    protected $lasth;
    protected $LineWidth;
    protected $fontpath;
    protected $FontFamily;
    protected $FontStyle;
    protected $FontSizePt;
    protected $FontSize;
    protected $DrawColor, $FillColor, $TextColor;
    protected $ColorFlag;
    protected $fonts;
    protected $FontFiles;
    protected $images;
    protected $PageLinks;
    protected $links;
    protected $AutoPageBreak;
    protected $PageBreakTrigger;
    protected $InHeader, $InFooter;
    protected $ZoomMode, $LayoutMode;
    protected $metadata;
    protected $AliasNbPages;
    protected $PDFVersion;

    function __construct($orientation='P', $unit='mm', $size='A4') {
        $this->page = 0;
        $this->n = 2;
        $this->buffer = '';
        $this->pages = array();
        $this->PageLinks = array();
        $this->offsets = array();
        $this->fonts = array();
        $this->FontFiles = array();
        $this->images = array();
        $this->links = array();
        $this->InHeader = false;
        $this->InFooter = false;
        $this->lasth = 0;
        $this->FontFamily = '';
        $this->FontStyle = '';
        $this->FontSizePt = 12;
        $this->DrawColor = '0 G';
        $this->FillColor = '0 g';
        $this->TextColor = '0 g';
        $this->ColorFlag = false;
        $this->compress = false;
        $this->PDFVersion = '1.3';
        
        if ($unit == 'pt') $this->k = 1;
        elseif ($unit == 'mm') $this->k = 72/25.4;
        elseif ($unit == 'cm') $this->k = 72/2.54;
        elseif ($unit == 'in') $this->k = 72;
        
        if ($size == 'A4') {
            $size = array(210, 297);
        }
        
        if ($orientation == 'P') {
            $this->DefOrientation = 'P';
            $this->w = $size[0];
            $this->h = $size[1];
        } else {
            $this->DefOrientation = 'L';
            $this->w = $size[1];
            $this->h = $size[0];
        }
        $this->CurOrientation = $this->DefOrientation;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;
        
        $margin = 28.35/$this->k;
        $this->SetMargins($margin, $margin);
        $this->cMargin = $margin/10;
        $this->LineWidth = .567/$this->k;
        $this->SetAutoPageBreak(true, 2*$margin);
        $this->ZoomMode = 'default';
        $this->LayoutMode = 'default';
        $this->metadata = array();
    }

    function SetMargins($left, $top, $right=null) {
        $this->lMargin = $left;
        $this->tMargin = $top;
        if ($right === null) $right = $left;
        $this->rMargin = $right;
    }

    function SetLeftMargin($margin) {
        $this->lMargin = $margin;
        if ($this->page > 0 && $this->x < $margin) $this->x = $margin;
    }

    function SetTopMargin($margin) {
        $this->tMargin = $margin;
    }

    function SetRightMargin($margin) {
        $this->rMargin = $margin;
    }

    function SetAutoPageBreak($auto, $margin=0) {
        $this->AutoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->PageBreakTrigger = $this->h - $margin;
    }

    function SetTitle($title, $isUTF8=false) {
        $this->metadata['Title'] = $isUTF8 ? $title : $this->_UTF8encode($title);
    }

    function AddPage($orientation='', $size='', $rotation=0) {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->FontFamily = '';
        $this->PageLinks[$this->page] = array();
    }

    function Header() {
        // To be implemented in your class
    }

    function Footer() {
        // To be implemented in your class
    }

    function SetFont($family, $style='', $size=0) {
        if ($family == '') $family = $this->FontFamily;
        $family = strtolower($family);
        $style = strtoupper($style);
        
        if ($size == 0) $size = $this->FontSizePt;
        
        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size/$this->k;
        
        if ($this->page > 0) {
            $this->_out(sprintf('BT /F1 %.2F Tf ET', $this->FontSizePt));
        }
    }

    function SetFontSize($size) {
        if ($this->FontSizePt == $size) return;
        $this->FontSizePt = $size;
        $this->FontSize = $size/$this->k;
        if ($this->page > 0) {
            $this->_out(sprintf('BT /F1 %.2F Tf ET', $this->FontSizePt));
        }
    }

    function SetDrawColor($r, $g=-1, $b=-1) {
        if (($r == 0 && $g == 0 && $b == 0) || $g == -1)
            $this->DrawColor = sprintf('%.3F G', $r/255);
        else
            $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r/255, $g/255, $b/255);
        if ($this->page > 0) $this->_out($this->DrawColor);
    }

    function SetFillColor($r, $g=-1, $b=-1) {
        if (($r == 0 && $g == 0 && $b == 0) || $g == -1)
            $this->FillColor = sprintf('%.3F g', $r/255);
        else
            $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255);
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
        if ($this->page > 0) $this->_out($this->FillColor);
    }

    function SetTextColor($r, $g=-1, $b=-1) {
        if (($r == 0 && $g == 0 && $b == 0) || $g == -1)
            $this->TextColor = sprintf('%.3F g', $r/255);
        else
            $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255);
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
    }

    function GetStringWidth($s) {
        $s = (string)$s;
        $w = 0;
        $l = strlen($s);
        for ($i = 0; $i < $l; $i++) {
            $w += 600; // Average character width
        }
        return $w * $this->FontSize / 1000;
    }

    function SetLineWidth($width) {
        $this->LineWidth = $width;
        if ($this->page > 0) {
            $this->_out(sprintf('%.2F w', $width*$this->k));
        }
    }

    function Line($x1, $y1, $x2, $y2) {
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',
            $x1*$this->k, ($this->h-$y1)*$this->k,
            $x2*$this->k, ($this->h-$y2)*$this->k));
    }

    function Rect($x, $y, $w, $h, $style='') {
        if ($style == 'F')
            $op = 'f';
        elseif ($style == 'FD' || $style == 'DF')
            $op = 'B';
        else
            $op = 'S';
        $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',
            $x*$this->k, ($this->h-$y)*$this->k,
            $w*$this->k, -$h*$this->k, $op));
    }

    function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
        $k = $this->k;
        if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AutoPageBreak) {
            $this->AddPage($this->CurOrientation);
        }
        
        $s = '';
        if ($fill || $border == 1) {
            if ($fill) {
                $op = ($border == 1) ? 'B' : 'f';
            } else {
                $op = 'S';
            }
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ',
                $this->x*$k, ($this->h-$this->y)*$k, $w*$k, -$h*$k, $op);
        }
        
        if (is_string($border)) {
            $x = $this->x;
            $y = $this->y;
            if (strpos($border, 'L') !== false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-$y)*$k, $x*$k, ($this->h-($y+$h))*$k);
            if (strpos($border, 'T') !== false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-$y)*$k, ($x+$w)*$k, ($this->h-$y)*$k);
            if (strpos($border, 'R') !== false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x+$w)*$k, ($this->h-$y)*$k, ($x+$w)*$k, ($this->h-($y+$h))*$k);
            if (strpos($border, 'B') !== false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-($y+$h))*$k, ($x+$w)*$k, ($this->h-($y+$h))*$k);
        }
        
        if ($txt !== '') {
            if ($align == 'R')
                $dx = $w - $this->cMargin - $this->GetStringWidth($txt);
            elseif ($align == 'C')
                $dx = ($w - $this->GetStringWidth($txt))/2;
            else
                $dx = $this->cMargin;
            
            $txt = $this->_escape($txt);
            $txt2 = str_replace(')', '\\)', str_replace('(', '\\(', str_replace('\\', '\\\\', $txt)));
            
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',
                ($this->x+$dx)*$k, ($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k, $txt2);
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

    function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        
        $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        
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
                $this->Cell($w, $h, substr($s, $j, $i-$j), $border, 2, $align, $fill);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
            
            $l += 600;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                    if ($this->ws > 0) {
                        $this->ws = 0;
                        $this->_out('0 Tw');
                    }
                    $this->Cell($w, $h, substr($s, $j, $i-$j), $border, 2, $align, $fill);
                } else {
                    if ($align == 'J') {
                        $this->ws = ($ns > 1) ? ($wmax-$ls)/1000*$this->FontSize/($ns-1) : 0;
                        $this->_out(sprintf('%.3F Tw', $this->ws*$this->k));
                    }
                    $this->Cell($w, $h, substr($s, $j, $sep-$j), $border, 2, $align, $fill);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        
        if ($this->ws > 0) {
            $this->ws = 0;
            $this->_out('0 Tw');
        }
        if ($border && strpos($border, 'B') !== false)
            $b .= 'B';
        $this->Cell($w, $h, substr($s, $j, $i-$j), $border, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    function Ln($h=null) {
        $this->x = $this->lMargin;
        if ($h === null)
            $this->y += $this->lasth;
        else
            $this->y += $h;
    }

    function Output($dest='', $name='', $isUTF8=false) {
        $this->_endpage();
        if ($this->state < 3) $this->_enddoc();
        
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
                if (PHP_SAPI != 'cli') {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="'.$name.'"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->buffer;
                break;
            case 'D':
                $this->_checkoutput();
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="'.$name.'"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->buffer;
                break;
            case 'F':
                $f = fopen($name, 'wb');
                if (!$f) $this->Error('Unable to create output file: '.$name);
                fwrite($f, $this->buffer, strlen($this->buffer));
                fclose($f);
                break;
            case 'S':
                return $this->buffer;
            default:
                $this->Error('Incorrect output destination: '.$dest);
        }
        return '';
    }

    protected function _beginpage($orientation, $size, $rotation) {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->FontFamily = '';
    }

    protected function _endpage() {
        $this->state = 1;
    }

    protected function _escape($s) {
        return $s;
    }

    protected function _putpages() {
        $nb = $this->page;
        
        for ($n = 1; $n <= $nb; $n++) {
            $this->_newobj();
            $this->_out('<</Type /Page');
            $this->_out('/Parent 1 0 R');
            $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->wPt, $this->hPt));
            $this->_out('/Resources 2 0 R');
            $this->_out('/Contents '.($this->n+1).' 0 R>>');
            $this->_out('endobj');
            
            $this->_newobj();
            $p = $this->pages[$n];
            $this->_out('<</Length '.strlen($p).'>>');
            $this->_putstream($p);
            $this->_out('endobj');
        }
        
        $this->offsets[1] = strlen($this->buffer);
        $this->_out('1 0 obj');
        $this->_out('<</Type /Pages');
        $kids = '/Kids [';
        for ($i = 0; $i < $nb; $i++)
            $kids .= (3+2*$i).' 0 R ';
        $kids .= ']';
        $this->_out($kids);
        $this->_out('/Count '.$nb);
        $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->wPt, $this->hPt));
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putfonts() {
        // Standard font
        $this->_newobj();
        $this->_out('<</Type /Font');
        $this->_out('/BaseFont /Helvetica');
        $this->_out('/Subtype /Type1');
        $this->_out('/Encoding /WinAnsiEncoding');
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putresources() {
        $this->_putfonts();
        
        $this->offsets[2] = strlen($this->buffer);
        $this->_out('2 0 obj');
        $this->_out('<</ProcSet [/PDF /Text]');
        $this->_out('/Font << /F1 3 0 R >>');
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putinfo() {
        $this->_out('/Producer '.$this->_textstring('FPDF '.FPDF_VERSION));
        if (!empty($this->metadata['Title']))
            $this->_out('/Title '.$this->_textstring($this->metadata['Title']));
        $this->_out('/CreationDate '.$this->_textstring('D:'.@date('YmdHis')));
    }

    protected function _putcatalog() {
        $this->_out('/Type /Catalog');
        $this->_out('/Pages 1 0 R');
    }

    protected function _putheader() {
        $this->_out('%PDF-'.$this->PDFVersion);
    }

    protected function _puttrailer() {
        $this->_out('/Size '.($this->n+1));
        $this->_out('/Root '.$this->n.' 0 R');
        $this->_out('/Info '.($this->n-1).' 0 R');
    }

    protected function _enddoc() {
        $this->_putheader();
        $this->_putpages();
        $this->_putresources();
        
        $this->_newobj();
        $this->_out('<<');
        $this->_putinfo();
        $this->_out('>>');
        $this->_out('endobj');
        
        $this->_newobj();
        $this->_out('<<');
        $this->_putcatalog();
        $this->_out('>>');
        $this->_out('endobj');
        
        $o = strlen($this->buffer);
        $this->_out('xref');
        $this->_out('0 '.($this->n+1));
        $this->_out('0000000000 65535 f ');
        for ($i = 1; $i <= $this->n; $i++)
            $this->_out(sprintf('%010d 00000 n ', $this->offsets[$i]));
        
        $this->_out('trailer');
        $this->_out('<<');
        $this->_puttrailer();
        $this->_out('>>');
        $this->_out('startxref');
        $this->_out($o);
        $this->_out('%%EOF');
        $this->state = 3;
    }

    protected function _newobj() {
        $this->n++;
        $this->offsets[$this->n] = strlen($this->buffer);
        $this->_out($this->n.' 0 obj');
    }

    protected function _out($s) {
        if ($this->state == 2)
            $this->pages[$this->page] .= $s."\n";
        else
            $this->buffer .= $s."\n";
    }

    protected function _putstream($s) {
        $this->_out('stream');
        $this->_out($s);
        $this->_out('endstream');
    }

    protected function _textstring($s) {
        return '('.$this->_escape($s).')';
    }

    protected function _UTF8encode($s) {
        return $s;
    }

    protected function _checkoutput() {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line))
                $this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
        }
        if (ob_get_length()) {
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
                ob_end_clean();
            } else {
                $this->Error("Some data has already been output, can't send PDF file");
            }
        }
    }

    function Error($msg) {
        throw new Exception('FPDF error: '.$msg);
    }
}

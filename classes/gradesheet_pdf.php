<?php
namespace local_gradesheet;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * TCPDF subclass carrying the ESSU header logos and the form footer.
 */
class gradesheet_pdf extends \pdf {
    public $report = [];

    public function Header() {
        global $CFG;
        $pageW = $this->getPageWidth();
        $topY = 10;
        $essulogo = $CFG->dirroot . '/local/gradesheet/pix/essu-header.png';
        $bagonglogo = $CFG->dirroot . '/local/gradesheet/pix/bagong-pilipinas.png';
        if (file_exists($essulogo)) {
            $this->Image($essulogo, 12, $topY, 55, 0);
        }
        if (file_exists($bagonglogo)) {
            $this->Image($bagonglogo, $pageW - 37, $topY, 25, 0);
        }
    }

    public function Footer() {
        $this->SetY(-24);
        $this->SetFont('helvetica', '', 7);
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->Ln(1);
        $this->Cell(0, 4, 'ESSU-ACAD-712.b  |  Version 5', 0, 0, 'L');
        $this->Cell(0, 4, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 1, 'R');
        $this->Cell(0, 4, 'Effectivity Date: March 15, 2024', 0, 0, 'L');
    }
}

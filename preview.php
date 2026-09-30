<?php
require_once('../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_item.php');

use local_gradesheet\helper;
use local_gradesheet\gradesheet_service;

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('group', 0, PARAM_INT);
$course   = get_course($courseid);
require_login($course);
$context  = context_course::instance($courseid);
require_capability('local/gradesheet:manage', $context);

if ($groupid > 0 && !helper::check_group_access($context, $groupid)) {
    redirect(
        new moodle_url('/local/gradesheet/index.php', ['courseid' => $courseid]),
        'You do not have permission to access the requested group.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$PAGE->set_url('/local/gradesheet/preview.php', array_filter(['courseid' => $courseid, 'group' => $groupid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_gradesheet'));
$PAGE->set_heading(get_string('pluginname', 'local_gradesheet'));

$data = gradesheet_service::compute_all_grades($courseid, $groupid);

$coursename    = $data['coursename'];
$semester      = $data['semester'];
$schoolyear    = $data['schoolyear'];
$coursenumber  = $data['coursenumber'];
$descriptive   = $data['descriptive'];
$courseandyear = $data['courseandyear'];
$schedule      = $data['schedule'];
$units         = $data['units'];
$instructor    = $data['instructor'];
$depthead      = $data['depthead'];
$registrar     = $data['registrar'];
$collegedean   = $data['collegedean'];
$rows          = $data['rows'];

$weightvalid = helper::validate_weight_sum($courseid);
if (!$weightvalid['valid']) {
    echo $OUTPUT->header();
echo '<div class="gradesheet-preview-page">';
    echo '<div class="container mt-4">';
    echo '<div class="alert alert-danger d-flex align-items-center" role="alert" style="font-size:16px; padding:20px 25px;">';
    echo '<span style="font-size:36px; margin-right:15px;">&#9888;</span>';
    echo '<div>';
    echo '<strong>Cannot Preview Grade Sheet</strong><br>';
    echo 'Category weights must sum to exactly <strong>100%</strong>. ';
    echo 'Current total: <strong>' . $weightvalid['total'] . '%</strong>. ';
    if ($weightvalid['count'] === 0) {
        echo 'No categories are defined.<br>';
    }
    echo 'Please fix this in Settings before printing.';
    echo '</div></div>';
    echo '<a href="course_settings.php?courseid=' . $courseid . '" class="btn btn-primary">Go to Settings</a> ';
    echo '<a href="index.php?courseid=' . $courseid . '" class="btn btn-secondary">Back to Grade Sheet</a>';
    echo '</div>';
    echo '</div>';
echo $OUTPUT->footer();
    exit;
}

if (empty($rows)) {
    echo $OUTPUT->header();
    echo '<div class="container mt-4">';
    echo '<div class="alert alert-warning" role="alert">Cannot preview PDF: No students are enrolled in this course.</div>';
    echo '<a href="index.php?courseid=' . $courseid . '" class="btn btn-secondary">Back to Grade Sheet</a>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

$PAGE->set_title('Report of Grades — ' . $coursename);
$PAGE->set_heading('Report of Grades Preview');

echo $OUTPUT->header();
echo '<div class="gradesheet-preview-page">';

$groupparam = $groupid ? '&group=' . $groupid : '';
?>

<div class="preview-toolbar">
    <div>
        <strong>Report of Grades Preview</strong>
        <span class="text-muted ml-2">- <?php echo s(format_string($coursename)); ?></span>
        <span class="badge badge-light text-dark ml-2" style="font-size:11px; font-weight:normal; opacity:0.9;">Single Source of Truth</span>
    </div>
    <div>
        <a href="index.php?courseid=<?php echo $courseid . $groupparam; ?>" class="btn btn-secondary btn-sm">← Back</a>
        <button id="btnPreviewPrint" onclick="printLoadedPdf()" class="btn btn-primary btn-sm ml-2" disabled>Print</button>
        <button id="btnPreviewDownload" onclick="downloadLoadedPdf()" class="btn btn-success btn-sm ml-2" disabled>Download PDF</button>
        <a href="course_settings.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary btn-sm ml-2">Settings</a>
    </div>
</div>

<div class="gradesheet-pdf-embed-wrapper">
    <div id="gsPreviewSpinner" class="gs-modal-spinner-wrap">
        <div class="gs-spinner"></div>
        <div class="gs-modal-spinner-text">Generating official PDF grade sheet...</div>
        <div class="small text-muted mt-1">Single source of truth: Loading official registrar-aligned document</div>
    </div>
    <div id="gsPreviewError" class="alert alert-danger gs-modal-error" role="alert">
        <span id="gsPreviewErrorText">Failed to load PDF preview.</span>
        <button type="button" class="btn btn-sm btn-outline-danger ml-3" onclick="loadPreviewPdf(true)">Retry</button>
    </div>
    <iframe id="gsPreviewIframe" class="gs-pdf-iframe" title="Report of Grades PDF Preview"></iframe>
</div>

<script>
(function() {
    var cachedPdfBlob = null;
    var cachedPdfUrl = null;
    var cachedPdfFilename = <?php echo json_encode('ReportOfGrades_' . str_replace(' ', '_', clean_filename($coursename)) . '_' . date('Ymd') . '.pdf'); ?>;
    var isFetching = false;

    var courseId = <?php echo (int)$courseid; ?>;
    var groupId  = <?php echo (int)$groupid; ?>;

    window.loadPreviewPdf = function(forceReload) {
        if (isFetching) return;
        if (cachedPdfBlob && !forceReload) return;

        var spinner  = document.getElementById('gsPreviewSpinner');
        var errBox   = document.getElementById('gsPreviewError');
        var iframe   = document.getElementById('gsPreviewIframe');
        var printBtn = document.getElementById('btnPreviewPrint');
        var dlBtn    = document.getElementById('btnPreviewDownload');

        if (spinner) spinner.style.display = 'flex';
        if (errBox) errBox.style.display = 'none';
        if (printBtn) printBtn.disabled = true;
        if (dlBtn) dlBtn.disabled = true;

        isFetching = true;

        var exportUrl = 'export.php?courseid=' + courseId + (groupId ? '&group=' + groupId : '') + '&action=preview';

        fetch(exportUrl, {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            var cd = response.headers.get('Content-Disposition') || '';
            var match = cd.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
            if (match && match[1]) {
                cachedPdfFilename = match[1].replace(/['"]/g, '').trim();
            }
            var contentType = response.headers.get('content-type') || '';
            if (contentType.indexOf('application/pdf') === -1) {
                throw new Error('Server returned non-PDF response. Please check course settings.');
            }
            return response.blob();
        })
        .then(function(blob) {
            cachedPdfBlob = blob;
            if (cachedPdfUrl) {
                URL.revokeObjectURL(cachedPdfUrl);
            }
            cachedPdfUrl = URL.createObjectURL(blob);
            if (iframe) {
                iframe.src = cachedPdfUrl;
            }
            if (spinner) spinner.style.display = 'none';
            if (printBtn) printBtn.disabled = false;
            if (dlBtn) dlBtn.disabled = false;
            isFetching = false;
        })
        .catch(function(err) {
            isFetching = false;
            if (spinner) spinner.style.display = 'none';
            if (errBox) {
                var errText = document.getElementById('gsPreviewErrorText');
                if (errText) {
                    errText.textContent = err.message || 'Failed to load PDF preview.';
                }
                errBox.style.display = 'block';
            }
        });
    };

    window.downloadLoadedPdf = function() {
        if (!cachedPdfBlob) {
            window.location.href = 'export.php?courseid=' + courseId + (groupId ? '&group=' + groupId : '');
            return;
        }
        var tempUrl = URL.createObjectURL(cachedPdfBlob);
        var a = document.createElement('a');
        a.style.display = 'none';
        a.href = tempUrl;
        a.download = cachedPdfFilename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function() {
            URL.revokeObjectURL(tempUrl);
        }, 2000);
    };

    window.printLoadedPdf = function() {
        var iframe = document.getElementById('gsPreviewIframe');
        if (iframe && iframe.contentWindow) {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                return;
            } catch (e) {
                console.warn('Direct iframe print encountered an issue, trying window fallback', e);
            }
        }
        if (cachedPdfUrl) {
            var printWin = window.open(cachedPdfUrl, '_blank');
            if (printWin) {
                printWin.focus();
            }
        }
    };

    // Auto-load PDF preview on page ready
    loadPreviewPdf(false);
})();
</script>

<?php
echo '</div>';
echo $OUTPUT->footer(); ?>
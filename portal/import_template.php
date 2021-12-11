<?php

/**
 * import_template.php
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2016-2021 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../interface/globals.php");

use OpenEMR\Core\Header;
use OpenEMR\Services\DocumentTemplates\DocumentTemplateService;

$templateService = new DocumentTemplateService();

$patient = json_decode($_POST['upload_pid'] ?? '');

$template_content = null;

if ($_POST['mode'] === 'save_profiles') {
    $profiles = json_decode($_POST['profiles']);
    $rtn = $templateService->saveAllProfileTemplates($profiles);
    if ($rtn) {
        echo xlt("Profiles successfully saved.");
    } else {
        echo xlt('Error! Profiles save failed. Check your Profile lists.');
    }
    exit;
}

if ($_REQUEST['mode'] === 'renderProfile') {
    echo renderProfileHtml();
    exit;
}

if ($_REQUEST['mode'] === 'getPdf') {
    if ($_REQUEST['docid']) {
        $template = $templateService->fetchTemplate($_REQUEST['docid']);
        echo "data:application/pdf;base64," . base64_encode($template['template_content']);
        exit();
    }
    die(xlt('Invalid File'));
}

if ($_POST['mode'] === 'get') {
    if ($_REQUEST['docid']) {
        $template = $templateService->fetchTemplate($_POST['docid']);
        echo $template['template_content'];
        exit();
    }
    die(xlt('Invalid File'));
}

if ($_POST['mode'] === 'send') {
    if (!empty($_POST['docid'])) {
        $pids_array = json_decode($_POST['docid']) ?: ['0'];
        // profiles are in an array with flag to indicate a group of template id's
        $ids = json_decode($_POST['checked']) ?: [];
        $master_ids = [];
        foreach ($ids as $id) {
            if (is_array($id)) {
                if ($id[1] !== true) {
                    continue;
                }
                $profile = $id[0];
                // get all template ids for this profile
                $rtn_ids = sqlStatement('SELECT `template_id` as id FROM `document_template_profiles` WHERE `profile` = ?', array($profile));
                while ($rtn_id = sqlFetchArray($rtn_ids)) {
                    $master_ids[] = $rtn_id['id'];
                }
                continue;
            }
            $master_ids[] = $id;
        }
        $master_ids = array_unique($master_ids);

        $last_id = $templateService->sendTemplate($pids_array, $master_ids, $_POST['category']);
        if ($last_id) {
            echo xlt('Templates Successfully sent to Locations.');
        } else {
            echo xlt('Error. Problem sending one or more templates. Some templates may not have been sent.');
        }
        exit;
    }
    die(xlt('Invalid Request'));
}

if ($_POST['mode'] === 'save') {
    if ($_POST['docid']) {
        if (stripos($_POST['content'], "<?php") === false) {
            $template = $templateService->updateTemplateContent($_POST['docid'], $_POST['content']);
            if ($_POST['service'] === 'window') {
                echo "<script>if (typeof parent.dlgopen === 'undefined') window.close(); else parent.dlgclose();</script>";
            }
        } else {
            die(xlt('Invalid Content'));
        }
    } else {
        die(xlt('Invalid File'));
    }
} elseif ($_POST['mode'] === 'delete') {
    if ($_POST['docid']) {
        $template = $templateService->deleteTemplate($_POST['docid']);
        exit($template);
    }
    die(xlt('Invalid File'));
} elseif ($_POST['mode'] === 'update_category') {
    if ($_POST['docid']) {
        $template = $templateService->updateTemplateCategory($_POST['docid'], $_POST['category']);
        echo xlt('Template Category successfully changed to new Category') . ' ' . text($_POST['category']);
        exit;
    }
    die(xlt('Invalid Request Parameters'));
} elseif (!empty($_FILES["template_files"])) {
    // so it is a template file import. create record(s).
    $import_files = $_FILES["template_files"];
    $total = count($_FILES['template_files']['name']);
    for ($i = 0; $i < $total; $i++) {
        if ($_FILES['template_files']['error'][$i] !== UPLOAD_ERR_OK) {
            header('refresh:3;url= import_template_ui.php');
            echo '<title>' . xlt('Error') . " ...</title><h4 style='color:red;'>" .
                xlt('An error occurred: Missing file to upload. Returning to form.') . '</h4>';
            exit;
        }
        // parse out what we need
        $name = preg_replace("/[^A-Z0-9.]/i", " ", $_FILES['template_files']['name'][$i]);
        if (preg_match("/(.*)\.(php|php7|php8|doc|docx)$/i", $name) !== 0) {
            die(xlt('Invalid file type.'));
        }
        $parts = pathinfo($name);
        $name = ucwords(strtolower($parts["filename"]));
        if (empty($patient)) {
            $patient = ['-1'];
        }
        // get em and dispose
        $success = $templateService->uploadTemplate($name, $_POST['template_category'], $_FILES['template_files']['tmp_name'][$i], $patient);
        if (!$success) {
            echo "<p>" . xlt("Unable to save files. Use back button!") . "</p>";
            exit;
        }
    }
    header("location: " . $_SERVER['HTTP_REFERER']);
    die();
}

if ($_REQUEST['mode'] === 'editor_render_html') {
    if ($_REQUEST['docid']) {
        $content = $templateService->fetchTemplate($_REQUEST['docid']);
        $template_content = $content['template_content'];
        if ($content['mime'] === 'application/pdf') {
            $content = "<iframe width='100%' height='100%' src='data:application/pdf;base64, " .
                attr(base64_encode($template_content)) . "'></iframe>";
            echo $content;
            exit;
        }
        renderEditorHtml($_REQUEST['docid'], $template_content);
    } else {
        die(xlt('Invalid File'));
    }
} elseif (!empty($_GET['templateHtml'] ?? null)) {
    renderEditorHtml($_REQUEST['docid'], $_GET['templateHtml']);
}

/**
 * @param $template_id
 * @param $content
 */
function renderEditorHtml($template_id, $content)
{
    $lists = [
    '{ParseAsHTML}', '{TextInput}', '{sizedTextInput:120px}', '{smTextInput}', '{TextBox:03x080}', '{CheckMark}', '{ynRadioGroup}', '{TrueFalseRadioGroup}', '{DatePicker}', '{DateTimePicker}', '{StandardDatePicker}', '{CurrentDate:"global"}', '{CurrentTime}', '{DOS}', '{ReferringDOC}', '{PatientID}', '{PatientName}', '{PatientSex}', '{PatientDOB}', '{PatientPhone}', '{Address}', '{City}', '{State}', '{Zip}', '{PatientSignature}', '{AdminSignature}', '{AcknowledgePdf: : }', '{EncounterForm:LBF}', '{Medications}', '{ProblemList}', '{Allergies}', '{ChiefComplaint}', '{DEM: }', '{HIS: }', '{LBF: }', '{GRP}{/GRP}'
    ];
    ?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['ckeditor']); ?>
</head>
<style>
  input:focus,
  input:active {
    outline: 0 !important;
    -webkit-appearance: none;
    box-shadow: none !important;
  }

  .list-group-item {
    font-size: .9rem;
  }
</style>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-10 px-1 sticky-top">
                <form class="sticky-top" action='./import_template.php' method='post'>
                    <input type="hidden" name="docid" value="<?php echo attr($template_id) ?>">
                    <input type='hidden' name='mode' value="save_profiles">
                    <input type='hidden' name='service' value='window'>
                    <textarea cols='80' rows='10' id='templateContent' name='content'><?php echo text($content) ?></textarea>
                    <div class="row btn-group mt-1 float-right">
                        <div class='col btn-group mt-1 float-right'>
                            <button type="submit" class="btn btn-sm btn-primary"><?php echo xlt("Save"); ?></button>
                            <button type='button' class='btn btn-sm btn-secondary' onclick='parent.window.close() || parent.dlgclose()'><?php echo xlt('Cancel'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-sm-2 px-0">
                <div class='h4'><?php echo xlt("Directives") ?></div>
                <ul class='list-group list-group-flush pl-1 mb-5'>
                    <?php
                    foreach ($lists as $list) {
                        echo '<input class="list-group-item p-1" value="' . attr($list) . '">';
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>
</body>
<script>
let isDialog = false;
let height = 550;
let max = 680;
    <?php if (!empty($_REQUEST['dialog'] ?? '')) { ?>
isDialog = true;
height = 425;
max = 600;
    <?php } ?>
let editor = '';
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.list-group-item').forEach(item => {
        item.addEventListener('mouseup', event => {
            let input = event.currentTarget;
            input.focus();
            input.select();
        })
    })
    editor = CKEDITOR.instances['templateContent'];
    if (editor) {
        editor.destroy(true);
    }
    CKEDITOR.disableAutoInline = true;
    CKEDITOR.config.extraPlugins = "preview,save,docprops,justify";
    CKEDITOR.config.allowedContent = true;
    //CKEDITOR.config.fullPage = true;
    CKEDITOR.config.height = height;
    CKEDITOR.config.width = '100%';
    CKEDITOR.config.resize_dir = 'both';
    CKEDITOR.config.resize_minHeight = max / 2;
    CKEDITOR.config.resize_maxHeight = max;
    CKEDITOR.config.resize_minWidth = '50%';
    CKEDITOR.config.resize_maxWidth = '100%';

    editor = CKEDITOR.replace('templateContent', {
        removeButtons: 'PasteFromWord'
    });
});
</script>
</html>
<?php }

/**
 *
 */
function renderProfileHtml()
{
    global $templateService;

    $category_list = $templateService->getDefaultCategories();
    $profile_list = $templateService->getDefaultProfiles();
    ?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['opener', 'sortablejs']); ?>
</head>
<style>
  .list-group-item .move-handle {
    cursor: move;
  }
  #trashDrop {
    border: 1px dashed #f60;
    min-height: 100px;
    position: relative;
  }
  #trashDrop::before {
    color: #ccc;
    font-size: 20px;
    content: 'Remove Drop Zone';
    display: block;
    text-align: center;
    padding-top: 10px;
  }
</style>
<script>
const profiles = <?php echo js_escape($profile_list); ?>;
document.addEventListener('DOMContentLoaded', function () {
    // init drag and drop
    let repository = document.getElementById('dragRepository');
    Sortable.create(repository, {
        group: {
            name: 'repo',
            handle: '.move-handle',
            pull: 'clone'
        },
        animation: 150,
        onAdd: function (evt) {
            let el = evt.item;
            el.parentNode.removeChild(el);
        }
    });
    let trashDrop = document.getElementById('trashDrop');
    Sortable.create(trashDrop, {
        group: {
            name: 'trash',
            put: 'repo'
        },
        animation: 150,
        onAdd: function (evt) {
            let el = evt.item;
            el.parentNode.removeChild(el);
        }
    });
    Object.keys(profiles).forEach(key => {
        let profileEl = profiles[key]['option_id']
        let id = document.getElementById(profileEl);
        Sortable.create(id, {
            group: {
                name: 'repo',
                delay: 1000,
                handle: '.move-handle',
                put: (to, from, dragEl, event) => {
                    for (let i = 0; i < to.el.children.length; i++) {
                        if (to.el.children[i].getAttribute('data-id') === dragEl.getAttribute('data-id')) {
                            return false
                        }
                    }
                    return true
                },
            },
            animation: 150
        });
    });
});
function submitProfiles() {
    top.restoreSession();
    let target = document.getElementById('edit-profiles');
    let profileTarget = target.querySelectorAll('ul');
    let profileArray = [];
    let listData = {};
    profileTarget.forEach((ulItem, index) => {
        let lists = ulItem.querySelectorAll('li');
        lists.forEach((item, index) => {
            //console.log({index, item})
            listData = {
                'profile': ulItem.dataset.profile,
                'id': item.dataset.id,
                'category': item.dataset.category,
                'name': item.dataset.name
            }
            profileArray.push(listData);
        });
    });

    const data = new FormData();
    data.append('profiles', JSON.stringify(profileArray));
    data.append('mode', 'save_profiles');
    fetch('./import_template.php', {
        method: 'POST',
        body: data,
    }).then(rtn => rtn.text()).then((rtn) => {
        (async (time) => {
            await asyncAlertMsg(rtn, time, 'success', 'lg');
        })(1000).then(rtn => {
            opener.document.edit_form.submit();
            dlgclose();
        });
    }).catch((error) => {
        console.error('Error:', error);
    });
}
</script>
<body>
    <div class='container-fluid'>
        <?php
        $templates = $templateService->getTemplateListAllCategories(-1);
        ?>
        <div class='row'>
            <div class='col-6'>
                <div class="sticky-top border-left border-right">
                    <h5 class='bg-dark text-light py-1 text-center'><?php echo xlt('Available Templates'); ?></h5>
                    <ul id='dragRepository' class='list-group mx-2 mb-2'>
                    <?php
                    foreach ($templates as $cat => $files) {
                        if (empty($cat)) {
                            $cat = xlt('General');
                        }
                        foreach ($files as $file) {
                            $template_id = attr($file['id']);
                            $title = $category_list[$cat]['title'] ?: $cat;
                            $title_esc = attr($title);
                            $this_name = attr($file['template_name']);
                            if ($file['mime'] === 'application/pdf') {
                                continue;
                            }
                            echo "<li class='list-group-item px-1 py-1 mb-2' data-id='$template_id' data-name='$this_name' data-category='$title_esc'>" .
                                '<i class="fa fa-arrows-alt move-handle mx-1"></i>' . "<strong>" . text($file['template_name']) .
                                '</strong>' . ' ' . xlt('in category') . ' ' .
                                '<strong>' . text($title) . '</strong>' . '</li>' . "\n";
                        }
                    }
                    ?>
                    </ul>
                    <div id='trashDrop' class='list-group'></div>
                    <div class="btn-group">
                        <button class='btn btn-primary btn-save my-2' onclick='return submitProfiles();'><?php echo xlt('Save Profiles'); ?></button>
                        <button class='btn btn-secondary btn-cancel my-2' onclick='dlgclose();'><?php echo xlt('Quit'); ?></button>
                    </div>
                </div>
            </div>
            <div class='col-6'>
                <div id="edit-profiles" class='control-group mx-1 border-left border-right'>
                <?php
                foreach ($profile_list as $profile => $profiles) {
                    $profile_items_list = $templateService->getProfileListByProfile($profile);
                    $profile_esc = attr($profile);
                    echo "<h5 class='bg-dark text-light py-1 text-center'>" . text($profiles['title']) . "</h5>\n";
                    echo "<ul id='$profile_esc' class='list-group mx-2 mb-2 droppable' data-profile='$profile_esc'>\n";
                    foreach ($profile_items_list as $cat => $files) {
                        if (empty($cat)) {
                            $cat = xlt('General');
                        }
                        foreach ($files as $file) {
                            $template_id = attr($file['id']);
                            $this_cat = attr($file['category']);
                            $title = $category_list[$file['category']]['title'] ?: $cat;
                            $this_name = attr($file['template_name']);
                            if ($file['mime'] === 'application/pdf') {
                                continue;
                            }
                            echo "<li class='list-group-item px-1 py-1 mb-2' data-id='$template_id' data-name='$this_name' data-category='$this_cat'>" .
                               '<i class="fa fa-arrows-alt move-handle mx-1"></i>' . text($file['template_name']) . ' ' . xlt('in category') . ' ' . text($title) . "</li>\n";
                        }
                    }
                    echo "</ul>\n";
                }
                ?>
                </div>
            </div>
        </div>
    </div>
    <hr />
</body>
</html>
<?php }

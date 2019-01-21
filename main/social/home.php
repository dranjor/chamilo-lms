<?php
/* For licensing terms, see /license.txt */

use ChamiloSession as Session;

/**
 * @package chamilo.social
 *
 * @author Julio Montoya <gugli100@gmail.com>
 * @autor Alex Aragon <alex.aragon@beeznest.com> CSS Design and Template
 */
$cidReset = true;

require_once __DIR__.'/../inc/global.inc.php';

$user_id = api_get_user_id();
$show_full_profile = true;
// social tab
Session::erase('this_section');
$this_section = SECTION_SOCIAL;

api_block_anonymous_users();

if (api_get_setting('allow_social_tool') != 'true') {
    $url = api_get_path(WEB_CODE_PATH).'auth/profile.php';
    header('Location: '.$url);
    exit;
}

$userGroup = new UserGroup();

//fast upload image
if (api_get_setting('profile', 'picture') == 'true') {
    $form = new FormValidator('profile', 'post', 'home.php', null, []);

    //	PICTURE
    $form->addElement('file', 'picture', get_lang('AddImage'));
    $form->addProgress();
    if (!empty($user_data['picture_uri'])) {
        $form->addElement(
            'checkbox',
            'remove_picture',
            null,
            get_lang('DelImage')
        );
    }
    $allowed_picture_types = api_get_supported_image_extensions();
    $form->addRule(
        'picture',
        get_lang('OnlyImagesAllowed').' ('.implode(
            ',',
            $allowed_picture_types
        ).')',
        'filetype',
        $allowed_picture_types
    );
    $form->addButtonSave(get_lang('SaveSettings'), 'apply_change');

    if ($form->validate()) {
        $user_data = $form->getSubmitValues();
        // upload picture if a new one is provided
        if ($_FILES['picture']['size']) {
            if ($new_picture = UserManager::update_user_picture(
                api_get_user_id(),
                $_FILES['picture']['name'],
                $_FILES['picture']['tmp_name']
            )) {
                $table_user = Database::get_main_table(TABLE_MAIN_USER);
                $sql = "UPDATE $table_user
                        SET 
                            picture_uri = '$new_picture' 
                        WHERE user_id =  ".api_get_user_id();

                $result = Database::query($sql);
            }
        }
    }
}

// Main post
if (!empty($_POST['social_wall_new_msg_main']) || !empty($_FILES['picture']['tmp_name'])) {
    $messageId = 0;
    $messageContent = $_POST['social_wall_new_msg_main'];
    if (!empty($_POST['url_content'])) {
        $messageContent = $_POST['social_wall_new_msg_main'].'<br /><br />'.$_POST['url_content'];
    }
    $idMessage = SocialManager::sendWallMessage(
        api_get_user_id(),
        api_get_user_id(),
        $messageContent,
        $messageId,
        MESSAGE_STATUS_WALL_POST
    );
    if (!empty($_FILES['picture']['tmp_name']) && $idMessage > 0) {
        $error = SocialManager::sendWallMessageAttachmentFile(
            api_get_user_id(),
            $_FILES['picture'],
            $idMessage,
            $fileComment = ''
        );
    }

    Display::addFlash(Display::return_message(get_lang('MessageSent')));

    $url = api_get_self();
    header('Location: '.$url);
    exit;
}

// Post comment
if (!empty($_POST['social_wall_new_msg']) && !empty($_POST['messageId'])) {
    $messageId = (int) $_POST['messageId'];
    $messageInfo = MessageManager::get_message_by_id($messageId);

    if (!empty($messageInfo)) {
        $res = SocialManager::sendWallMessage(
            api_get_user_id(),
            $messageInfo['user_receiver_id'],
            $_POST['social_wall_new_msg'],
            $messageId,
            MESSAGE_STATUS_WALL
        );
        Display::addFlash(Display::return_message(get_lang('MessageSent')));
    }

    $url = api_get_self();
    header('Location: '.$url);
    exit;
}

$locale = api_get_language_isocode();
$javascriptDir = api_get_path(LIBRARY_PATH).'javascript/';
// Add Jquery scroll pagination plugin
$htmlHeadXtra[] = api_get_js('jscroll/jquery.jscroll.js');
// Add Jquery Time ago plugin
$htmlHeadXtra[] = api_get_asset('jquery-timeago/jquery.timeago.js');
$timeAgoLocaleDir = $javascriptDir.'jquery-timeago/locales/jquery.timeago.'.$locale.'.js';
if (file_exists($timeAgoLocaleDir)) {
    $htmlHeadXtra[] = api_get_js('jquery-timeago/locales/jquery.timeago.'.$locale.'.js');
}
$socialAjaxUrl = api_get_path(WEB_AJAX_PATH).'social.ajax.php';

$htmlHeadXtra[] = '<script>
$(document).ready(function(){
    var container = $("#wallMessages");
    container.jscroll({
        loadingHtml: "<div class=\"well_border\">'.get_lang('Loading').' </div>",
        nextSelector: "a.nextPage:last",
        contentSelector: "",
        callback: timeAgo
    });
    timeAgo();
    
    $(".delete_message").on("click", function() {
        var id = $(this).attr("id");
        id = id.split("_")[1]; 
        
        $.ajax({
            url: "'.$socialAjaxUrl.'?a=delete_message" + "&id=" + id,
            success: function (result) {
                if (result) {
                    $("#message_" + id).parent().parent().parent().parent().html(result);
                }
            }
        });        
    });
    
    $(".delete_comment").on("click", function() {
        var id = $(this).attr("id");
        id = id.split("_")[1]; 
        
        $.ajax({
            url: "'.$socialAjaxUrl.'?a=delete_message" + "&id=" + id,
            success: function (result) {
                if (result) {
                    $("#message_" + id).parent().parent().parent().html(result);
                }
            }
        });        
    });
    
});

function timeAgo() {
    $(".timeago").timeago();
}
</script>';


//Block Menu
$social_menu_block = SocialManager::show_social_menu('home');

$social_search_block = Display::panel(
    UserManager::get_search_form(''),
    get_lang('SearchUsers')
);

$results = $userGroup->get_groups_by_user($user_id,
    [
        GROUP_USER_PERMISSION_ADMIN,
        GROUP_USER_PERMISSION_READER,
        GROUP_USER_PERMISSION_MODERATOR,
        GROUP_USER_PERMISSION_HRM,
    ]
);

$myGroups = [];
if (!empty($results)) {
    foreach ($results as $result) {
        $id = $result['id'];
        $result['description'] = Security::remove_XSS($result['description'], STUDENT, true);
        $result['name'] = Security::remove_XSS($result['name'], STUDENT, true);

        /*if ($result['count'] == 1) {
            $result['count'] = '1 '.get_lang('Member');
        } else {
            $result['count'] = $result['count'].' '.get_lang('Members');
        }*/
        $group_url = "group_view.php?id=$id";

        $link = Display::url(
            api_ucwords(cut($result['name'], 40, true)),
            $group_url
        );

        $result['name'] = $link;

        $picture = $userGroup->get_picture_group(
            $id,
            $result['picture'],
            null,
            GROUP_IMAGE_SIZE_BIG
        );

        $result['picture'] = '<img class="img-responsive" src="'.$picture['file'].'" />';
        $group_actions = '<div class="group-more"><a class="btn btn-default" href="groups.php?#tab_browse-2">'.
            get_lang('SeeMore').'</a></div>';
        $group_info = '<div class="description"><p>'.cut($result['description'], 120, true)."</p></div>";
        $myGroups[] = [
            'url' => Display::url(
                $result['picture'],
                $group_url
            ),
            'name' => $result['name'],
            'description' => $group_info.$group_actions,
        ];
    }
}


$social_group_block = '';
if (count($myGroups) > 0) {
    $social_group_block .= '<div class="list-group">';
    foreach ($myGroups as $group) {
        $social_group_block .= ' <li class="list-group-item">';
        $social_group_block .= $group['name'];
        //$social_group_block .= '<div class="col-md-9">'.$groups_newest[$i][1];
        $social_group_block .= '</li>';
    }
    $social_group_block .= "</div>";
}

$form = new FormValidator(
    'find_groups_form',
    'get',
    api_get_path(WEB_CODE_PATH).'social/search.php?search_type=2',
    null,
    null,
    'inline'
);
$form->addHidden('search_type', 2);

$form->addText(
    'q',
    get_lang('Search'),
    false,
    [
        'aria-label' => get_lang('SearchGroups'),
    ]
);
$form->addButtonSearch(get_lang('Search'));

$social_group_block .= $form->returnForm();

/*$list = count($groups_pop);
if ($list > 0) {
    $social_group_block .= '<div class="list-group-newest">';
    $social_group_block .= '<div class="group-title">'.get_lang('Popular').'</div>';

    for ($i = 0; $i < $list; $i++) {
        $social_group_block .= '<div class="row">';
        $social_group_block .= '<div class="col-md-3">'.$groups_pop[$i][0].'</div>';
        $social_group_block .= '<div class="col-md-9">'.$groups_pop[$i][1];
        $social_group_block .= $groups_pop[$i][2].'</div>';
        $social_group_block .= "</div>";
    }
    $social_group_block .= "</div>";
}*/
// My friends
$friend_html = SocialManager::listMyFriendsBlock(
    $user_id,
    '',
    $show_full_profile
);

// Block Social Sessions
$social_session_block = null;
$user_info = api_get_user_info($user_id);
$sessionList = SessionManager::getSessionsFollowedByUser($user_id, $user_info['status']);

if (count($sessionList) > 0) {
    $social_session_block = $sessionList;
}

$social_group_block = Display::panelCollapse(
    get_lang('MyGroups'),
    $social_group_block,
    'sm-groups',
    null,
    'grups-acordion',
    'groups-collapse'
);

$wallSocialAddPost = SocialManager::getWallForm($show_full_profile, api_get_self());
// Social Post Wall
$posts = SocialManager::getMyWallMessages($user_id);
$social_post_wall_block = empty($posts) ? '<p>'.get_lang('NoPosts').'</p>' : $posts;
$socialAutoExtendLink = '';
if (!empty($posts)) {
    $socialAutoExtendLink = Display::url(
        get_lang('SeeMore'),
        $socialAjaxUrl.'?u='.$user_id.'&a=list_wall_message&start=10&length=5',
        [
            'class' => 'nextPage next',
        ]
    );
}

$form = new FormValidator(
    'find_friends_form',
    'get',
    api_get_path(WEB_CODE_PATH).'social/search.php?search_type=1',
    null,
    null,
    'inline'
);
$form->addHidden('search_type', 1);
$form->addText(
    'q',
    get_lang('Search'),
    false,
    [
        'aria-label' => get_lang('SearchUsers'),
    ]
);
$form->addButtonSearch(get_lang('Search'));

$tpl = new Template(get_lang('SocialNetwork'));
SocialManager::setSocialUserBlock($tpl, $user_id, 'home');
$tpl->assign('social_wall_block', $wallSocialAddPost);
$tpl->assign('social_post_wall_block', $social_post_wall_block);
$tpl->assign('social_menu_block', $social_menu_block);
$tpl->assign('social_auto_extend_link', $socialAutoExtendLink);


$tpl->assign('search_friends_form', $form->returnForm());

$tpl->assign('social_friend_block', $friend_html);
//$tpl->assign('session_list', $social_session_block);
$tpl->assign('social_search_block', $social_search_block);
$tpl->assign('social_skill_block', SocialManager::getSkillBlock($user_id));
$tpl->assign('social_group_block', $social_group_block);
$social_layout = $tpl->get_template('social/home.tpl');
$tpl->display($social_layout);

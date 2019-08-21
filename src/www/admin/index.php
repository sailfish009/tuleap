<?php
/**
  * Copyright 1999-2000 (c) The SourceForge Crew
  * Copyright (c) Enalean, 2011 - Present. All Rights Reserved.
  *
  * This file is a part of Tuleap.
  *
  * Tuleap is free software; you can redistribute it and/or modify
  * it under the terms of the GNU General Public License as published by
  * the Free Software Foundation; either version 2 of the License, or
  * (at your option) any later version.
  *
  * Tuleap is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  * GNU General Public License for more details.
  *
  * You should have received a copy of the GNU General Public License
  * along with Tuleap. If not, see <http://www.gnu.org/licenses/
  */

use Tuleap\Admin\Homepage\NbUsersByStatusBuilder;
use Tuleap\Admin\Homepage\StatisticsBadgePresenter;
use Tuleap\Admin\Homepage\StatisticsPresenter;
use Tuleap\Admin\Homepage\UserCounterDao;
use Tuleap\Admin\Homepage\UsersStatisticsPresenter;
use Tuleap\Layout\IncludeAssets;

require_once __DIR__ . '/../include/pre.php';
require_once __DIR__ . '/admin_utils.php';

$request = HTTPRequest::instance();
$request->checkUserIsSuperUser();

$assets_path    = ForgeConfig::get('tuleap_dir') . '/src/www/assets';
$include_assets = new IncludeAssets($assets_path, '/assets');

$GLOBALS['HTML']->includeFooterJavascriptFile(
    $include_assets->getFileURL('site-admin-system-events-admin-homepage.js')
);

$GLOBALS['HTML']->includeFooterJavascriptFile(
    $include_assets->getFileURL('site-admin-generate-pie-charts.js')
);

$abc_array = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','0','1','2','3','4','5','6','7','8','9');

$em = EventManager::instance();

$project_manager = ProjectManager::instance();

// Get various number of users and projects from status
$project_dao = new ProjectDao();
$projects_by_status = $project_dao->getProjectsGroupByStatus();
$project_count = [];
foreach($projects_by_status as $projects) {
    $project_count[$projects['status']] = $projects['project_nb'];
}

$pending_projects      = null;
$project_pending_count = 0;
if (isset($project_count[Project::STATUS_PENDING])) {
    $pending_projects      = $project_count[Project::STATUS_PENDING];
    $project_pending_count = $pending_projects;
}

$builder = new NbUsersByStatusBuilder(new UserCounterDao());
$nb_users_by_status = $builder->getNbUsersByStatusBuilder();

$realpending_users = $nb_users_by_status->getNbPending();
$validated_users   = $nb_users_by_status->getNbAllValidated();
$restricted_users  = $nb_users_by_status->getNbRestricted();
$actif_users       = $nb_users_by_status->getNbActive();
$hold_users        = $nb_users_by_status->getNbSuspended();
$deleted_users     = $nb_users_by_status->getNbDeleted();

db_query("SELECT COUNT(DISTINCT(p.user_id)) AS count
          FROM user_preferences p
          JOIN user u USING (user_id)
          WHERE preference_name = 'use_lab_features'
            AND preference_value = 1
            AND (status = 'A'
              OR status = 'R')");
$row = db_fetch_array();
$mode_lab = $row['count'];

if($GLOBALS['sys_user_approval'] == 1){
    $pending_users = $realpending_users;

}else{
    $pending_users = $realpending_users + $validated_users ;
}



db_query("SELECT count(*) AS count FROM user WHERE status='V' OR status='W'");
$row = db_fetch_array();
$validated_users = $row['count'];

$purifier = Codendi_HTMLPurifier::instance();
$statistics_users_graph = array();

if ($actif_users > 0) {
    $statistics_users_graph[] = array( 'key'=> 'active', 'label' => $Language->getText('admin_main', 'statusactif_user'), 'count' => $actif_users);
}

if ($hold_users > 0) {
    $statistics_users_graph[] = array( 'key'=> 'suspended', 'label' => $Language->getText('admin_main', 'statushold_user'), 'count' => $hold_users);
}

if ($deleted_users > 0) {
    $statistics_users_graph[] = array( 'key'=> 'deleted', 'label' => $Language->getText('admin_main', 'statusdeleted_user'), 'count' => $deleted_users);
}

if ($validated_users + $realpending_users > 0) {
    $statistics_users_graph[] = array( 'key'=> 'waiting', 'label' => $Language->getText('admin_main', 'statuspending_user'), 'count' => $validated_users + $realpending_users);
}

if (ForgeConfig::areRestrictedUsersAllowed() && $restricted_users > 0) {
    $statistics_users_graph[] = array( 'key'=> 'restricted', 'label' => $Language->getText('admin_main', 'statusrestricted_user'), 'count' => $restricted_users);
}

function stats_getactiveusers($since)
{
    $time_totest=time()-$since;

    $res_count = db_query("SELECT count(*) AS count FROM user_access WHERE last_access_date> $time_totest ");

    if (db_numrows($res_count) > 0) {
        $row_count = db_fetch_array($res_count);
        return $row_count['count'];
    } else {
        return "error";
    }
}

$additional_statistics = array(
    new StatisticsPresenter(
        $GLOBALS['Language']->getText('admin_main', 'mode_lab_users'),
        array(
            new StatisticsBadgePresenter(
                "$mode_lab ". $GLOBALS['Language']->getText('admin_main', 'mode_lab_users_nb_users'),
                StatisticsBadgePresenter::LEVEL_SECONDARY
            )
        )
    )
);
EventManager::instance()->processEvent(
    Event::GET_SITEADMIN_HOMEPAGE_USER_STATISTICS,
    array(
        'nb_users_by_status'    => $nb_users_by_status,
        'additional_statistics' => &$additional_statistics
    )
);

$nb_seconds_in_a_day        = 84600;
$nb_seconds_in_a_week       = 592200;
$nb_seconds_in_a_month      = 2678400;
$nb_seconds_in_three_months = 8031600;

$user_statistics_presenter = new UsersStatisticsPresenter(
    $statistics_users_graph,
    stats_getactiveusers($nb_seconds_in_a_day),
    stats_getactiveusers($nb_seconds_in_a_week),
    stats_getactiveusers($nb_seconds_in_a_month),
    stats_getactiveusers($nb_seconds_in_three_months),
    $additional_statistics
);
$renderer = TemplateRendererFactory::build()->getRenderer(ForgeConfig::get('codendi_dir') .'/src/templates/admin/homepage/');

$user_stats = new Widget_Static($Language->getText('admin_main', 'stat_users'));
$user_stats->setIcon('fa-pie-chart');
$user_stats->setAdditionalClass('siteadmin-homepage-statistics');
$user_stats->setContent($renderer->renderToString('users-statistics', $user_statistics_presenter));

$project_stats = new Widget_Static($Language->getText('admin_main', 'stat_projects'));
$project_stats->setIcon('fa-pie-chart');
$project_stats->setAdditionalClass('siteadmin-homepage-statistics');

$statistics_projects_graph = [];

if (isset($project_count[Project::STATUS_ACTIVE])) {
    $statistics_projects_graph[] = ['key' => 'active', 'label' => $Language->getText('admin_main', 'sstat_reg_act_g'), 'count' => $project_count[Project::STATUS_ACTIVE]];
}

if (isset($project_count[Project::STATUS_PENDING])) {
    $statistics_projects_graph[] = ['key' => 'pending', 'label' => $Language->getText('admin_main', 'sstat_pend_g'), 'count' => $project_count[Project::STATUS_PENDING]];
}

if (isset($project_count[Project::STATUS_DELETED])) {
    $statistics_projects_graph[] = ['key' => 'deleted', 'label' => $Language->getText('admin_main', 'sstat_deleted'), 'count' => $project_count[Project::STATUS_DELETED]];
}

if (isset($project_count[Project::STATUS_SUSPENDED])) {
    $statistics_projects_graph[] = ['key' => 'suspended', 'label' => _('suspended'), 'count' => $project_count[Project::STATUS_SUSPENDED]];
}

$project_stats->setContent('
    <section class="tlp-pane-section siteadmin-homepage-statistics-section-last siteadmin-homepage-statistics-section-graph">
        <div class="tlp-property">
            <div id="siteadmin-homepage-projects"
                 class="siteadmin-homepage-pie-chart"
                 data-statistics="'. $purifier->purify(json_encode($statistics_projects_graph)) .'"></div>
        </div>

    </section>
    <div class="tlp-pane-spacer"></div>
    <section class="tlp-pane-section">
        <a href="/admin/grouplist.php" class="tlp-button-primary tlp-button-outline tlp-button-wide" title="'.$Language->getText('admin_main', 'homepage_all_projects').'">'.$Language->getText('admin_main', 'homepage_all_projects').'</a>
    </section>
');

if ($GLOBALS['sys_user_approval'] == 1) {
    $pending_action = '<p class="siteadmin-homepage-no-validation">'.$Language->getText('admin_main', 'review_pending_users_empty').'</p>';
    $pending_class  = '';

    if ($pending_users != 0) {
        $pending_action = '<a href="approve_pending_users.php?page=pending" class="tlp-button-primary tlp-button-wide">'.$Language->getText('admin_main', 'review_pending_users').'</a>';
        $pending_class  = 'tlp-text-warning';
    }

    $wUser = new Widget_Static($Language->getText('admin_main', 'header_user'));
    $wUser->setAdditionalClass('siteadmin-homepage-users');
    $wUser->setIcon('fa-group');
    $wUser->setContent('
        <section class="tlp-pane-section">
            <div class="siteadmin-homepage-validation">
                <svg width="127px" height="102px" viewBox="0 0 127 102" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <g stroke="none" stroke-width="1" fill-rule="evenodd">
                        <g class="siteadmin-homepage-users-icon" transform="translate(-779.000000, -253.000000)">
                            <g transform="translate(779.000000, 253.000000)">
                                <path d="M119.806104,65.6033953 C119.556503,68.1001413 117.055516,70.0622224 115.308161,70.0622226 L105.368009,70.0622226 C105.715937,71.9398303 105.999973,73.6835074 105.999973,74.372062 C105.999973,78.3949476 105.999973,81.3901638 105.999973,81.3901638 C105.999974,84.7071437 103.313967,87.3960877 100.0027,87.3960882 C100.0027,87.3960882 96.9030645,87.396089 92.9648396,87.396089 L72.9999996,87.396089 C72.9999997,91.5605453 73,95.2642843 73,95.2642843 C73.0000002,98.5749831 70.3126837,101.258835 67.0029252,101.258836 C67.0029252,101.258836 62.2807193,101.258837 57.640139,101.258837 L15.3598606,101.258837 C10.7192802,101.258837 5.99707479,101.260129 5.99707479,101.260129 C2.68498184,101.260856 0,98.5724161 0,95.2656465 L0,84.8665272 C0,82.6302421 2.63827355,71.0273665 2.63827355,71.0273665 C2.87775012,69.9551902 3.73945498,68.4849511 4.55532951,67.7503525 L20.6487496,53.2601296 C20.6487496,53.2601296 24.6270902,59.3390509 35.494745,59.3390505 C46.3623997,59.33905 49.6794513,53.2601296 49.6794513,53.2601296 L58.4038008,59.9603837 L68.6648009,49.2601296 C68.6648009,49.2601296 69.9979972,53.2954369 79.2208238,53.2954366 C88.4436505,53.2954362 89.1433292,49.2601296 89.1433292,49.2601296 L99.1229107,57.8822726 C100.077561,56.5633601 101.124491,55.4691138 102.073975,54.8408175 C102.073975,54.8408175 104.437915,56.9324093 107.634712,56.9324092 C110.83151,56.9324091 113.260582,54.3490026 113.260582,54.3490026 C114.784544,55.3339568 116.205459,56.9008765 117.33309,58.6226376 C117.564091,58.3894722 117.794454,58.1939988 118.014857,58.0481534 C118.014857,58.0481534 119.188213,59.0863284 120.774964,59.0863283 C122.361715,59.0863283 123.567401,57.8040379 123.567401,57.8040379 C125.3097,58.9301062 126.780339,61.5793315 126.826373,63.1890737 C126.826373,63.1890737 126.826373,62.8597052 126.826373,63.1890737 C126.826373,63.9348138 126.826373,63.1890737 126.826373,63.1890737 C126.826373,64.5224665 125.085918,65.6033953 124.583729,65.6033953 L119.806104,65.6033953 Z M112.542976,52.2115333 C111.282507,53.8313144 109.445771,54.8515929 107.400677,54.8515929 C105.081661,54.8515929 103.030557,53.539697 101.783792,51.5294154 C101.707107,51.6140041 101.613185,51.6793457 101.5,51.7224768 C100.870013,51.9625439 97.8257801,52.5523451 98,52.2203978 C98.1742199,51.8884504 99.3667779,49.9364418 99.2462984,50.0627401 C98.6154565,50.72405 97.155377,50.1978778 97.5,49.7601296 C98.0226597,49.0962349 98.8525391,46.577293 98.8525391,45.08353 C98.8525391,43.9741142 98.8525391,40.2702936 102.336937,38.1126359 C103.020139,37.6895745 103.310362,38.0512888 104.079136,37.9466622 C104.739429,37.8567994 104.427576,37.1167939 106.169775,36.6188729 C107.911975,36.1209518 109.828394,36.2869255 112.267472,37.7806885 C113.543819,38.5623604 115.673999,42.1593043 115.958407,44.5318518 C116.285559,47.2609779 115.780461,48.4030034 117,50.0627401 C117.377161,50.5760387 115.00715,50.7918913 114.5,50.8884504 C114.5,50.8884504 114.964352,52.2203978 115.661232,52.2203978 C115.913391,52.2203978 114.166489,52.3123899 112.542976,52.2115333 Z M49.5591255,80.5234874 C49.5591255,73.3750335 43.7666261,67.5753446 36.6270338,67.5753446 C29.4874415,67.5753446 23.6949421,73.3750335 23.6949421,80.5234874 C23.6949421,87.6719412 29.4874415,93.4716301 36.6270338,93.4716301 C43.7666261,93.4716301 49.5591255,87.6719412 49.5591255,80.5234874 Z M111.752462,63.1393084 C111.752462,61.0368515 110.04881,59.3310847 107.948959,59.3310847 C105.849109,59.3310847 104.145457,61.0368515 104.145457,63.1393084 C104.145457,65.2417652 105.849109,66.947532 107.948959,66.947532 C110.04881,66.947532 111.752462,65.2417652 111.752462,63.1393084 Z M122.818835,62.1671625 C122.818835,61.1235946 121.973217,60.2769263 120.930942,60.2769263 C119.888668,60.2769263 119.043049,61.1235946 119.043049,62.1671625 C119.043049,63.2107304 119.888668,64.0573987 120.930942,64.0573987 C121.973217,64.0573987 122.818835,63.2107304 122.818835,62.1671625 Z M89.2310712,70.2601236 C89.2310712,65.1574816 85.0963238,61.0176021 80.0000072,61.0176021 C74.9036905,61.0176021 70.7689431,65.1574816 70.7689431,70.2601236 C70.7689431,75.3627657 74.9036905,79.5026451 80.0000072,79.5026451 C85.0963238,79.5026451 89.2310712,75.3627657 89.2310712,70.2601236 Z M49.1943522,48.5029835 C45.6059001,52.9339983 40.5870219,55.6866732 35.0326082,55.6866732 C29.5636538,55.6866732 24.6138823,53.0180511 21.0375108,48.7063122 C20.8118209,49.2084746 20.4730407,49.5709133 20,49.7601296 C18.1919791,50.483338 10.5,48.2601296 13,48.7601296 C15.5,49.2601296 16,43.7601296 16,44.7601296 C16,47.4098425 6.90230741,45.2926938 8.5,44.7601296 C14.5,42.7601296 10.5,30.7601296 10.5,26.2601296 C10.5,22.9179856 10.5,11.7601296 20.5,5.26012959 C22.4607439,3.98564607 23.2936673,5.07531997 25.5,4.76012959 C27.3949986,4.48941551 26.5,2.26012959 31.5,0.760129589 C36.5,-0.739870411 42,-0.239870411 49,4.26012959 C52.663033,6.6149365 58.7765138,17.4508236 59.5927456,24.5981848 C60.5316518,32.8197481 55.5,40.7601296 61,46.2601296 C56.5,46.7601296 56,45.7601296 54,44.7601296 C54,44.7601296 55.5,47.7601296 57.5,47.7601296 C58.6226354,47.7601296 51.6870111,50.0232597 49.1943522,48.5029835 Z M123.907888,55.5749281 C123.572521,56.9288471 122.36035,57.9316392 120.916364,57.9316392 C119.472717,57.9316392 118.260776,56.9293178 117.925076,55.5758814 C117.824144,55.5609687 117.753014,55.5458433 117.717529,55.5309236 C117.473734,55.4284187 117.09439,54.7615394 117.254391,53.6862549 C117.307491,53.3293963 117.349835,52.7200023 117.496755,52.3748439 C117.680441,51.9433078 117.838917,51.025168 118.232669,50.796804 C118.293735,50.7613881 119.108304,50.8034002 119.189358,50.796804 C119.541509,50.7681465 119.643242,49.99276 119.998865,49.9613712 C120.60128,49.9081992 120.869091,49.6715688 121.323511,49.4972418 C121.700422,49.3526489 122.052153,49.736905 122.353792,49.6828935 C122.864582,49.5914314 123.384072,48.7179178 123.384072,49.0331124 C123.384072,49.5900677 123.635109,50.0522926 123.310481,50.7039782 C123.269421,50.7864054 124.079447,51.1879751 124.046395,51.2609334 C123.942824,51.4895563 124.193122,51.8523354 124.340761,52.3748439 C124.612735,53.3373842 124.483857,55.0161376 124.340761,55.3771304 C124.308344,55.4589094 124.152172,55.5239911 123.907888,55.5749281 Z M92.239076,35.2564701 C91.9055923,43.8468966 86.0935039,50.6866732 78.972514,50.6866732 C71.7279401,50.6866732 65.8380935,43.6074293 65.6935614,34.8076569 C64.8326449,33.8704659 63.9915672,31.7645588 64.5,28.7601296 C64.7142927,27.4938317 65.9070887,24.9849096 66.5,23.7601296 C67.2412904,22.2288421 66.8589309,19.3173753 68.4479677,18.5070355 C68.6944045,18.3813634 71.9817039,18.5304415 72.3088105,18.5070355 C73.7299609,18.4053455 74.1405164,15.6539194 75.5756775,15.5425373 C78.0068042,15.3538589 79.087591,15.514186 80.9214598,14.8955939 C82.4425343,14.3825121 83.861988,12.7460287 85.0792905,12.5543713 C87.14065,12.2298218 90,10.641675 90,11.7601296 C90,13.7364617 90.2502119,13.8651681 88.9401332,16.1776468 C88.7744305,16.4701362 91.0433972,18.8950894 90.9100123,19.1539789 C90.4920363,19.9652375 93.5,19.7601296 92.5,23.2601296 C92.0595784,24.8016053 93.4320714,27.4923386 93.5,29.7601296 C93.5863068,32.6414748 93.4211102,34.0433279 93.0979639,34.7601296 C93.0123093,34.9501284 92.7125552,35.1147278 92.239076,35.2564701 Z M121.874889,61.6946034 C121.874889,62.0687127 121.619237,62.2114649 121.432414,62.3172984 C121.316879,62.3837521 121.245591,62.5043531 121.245591,62.5609617 C121.245591,62.6052641 121.211177,62.6397215 121.166929,62.6397215 L120.694956,62.6397215 C120.650708,62.6397215 120.616294,62.6052641 120.616294,62.5609617 L120.616294,62.4723569 C120.616294,62.2336161 120.854738,62.0293328 121.02927,61.9505729 C121.17922,61.8816581 121.245591,61.8176657 121.245591,61.6896809 C121.245591,61.5813862 121.100558,61.4804751 120.943233,61.4804751 C120.854738,61.4804751 120.773618,61.5100101 120.731829,61.539545 C120.685123,61.5740024 120.635959,61.6183048 120.520424,61.7659795 C120.505675,61.7856695 120.481093,61.7955145 120.458969,61.7955145 C120.441762,61.7955145 120.424555,61.790592 120.412264,61.780747 L120.087782,61.5346225 C120.055825,61.5100101 120.045993,61.4657076 120.068116,61.4312502 C120.279521,61.0792922 120.579421,60.907005 120.980106,60.907005 C121.402916,60.907005 121.874889,61.2441956 121.874889,61.6946034 Z M121.245591,63.3485601 C121.245591,63.3928625 121.211177,63.42732 121.166929,63.42732 L120.694956,63.42732 C120.650708,63.42732 120.616294,63.3928625 120.616294,63.3485601 L120.616294,62.8760011 C120.616294,62.8316987 120.650708,62.7972412 120.694956,62.7972412 L121.166929,62.7972412 C121.211177,62.7972412 121.245591,62.8316987 121.245591,62.8760011 L121.245591,63.3485601 Z M109.850711,62.1872525 C109.850711,62.9409634 109.335653,63.2285636 108.959265,63.4417845 C108.726498,63.5756673 108.582877,63.8186399 108.582877,63.9326883 C108.582877,64.0219435 108.513542,64.0913643 108.424397,64.0913643 L107.473522,64.0913643 C107.384377,64.0913643 107.315042,64.0219435 107.315042,63.9326883 L107.315042,63.7541778 C107.315042,63.2731912 107.795433,62.8616254 108.147058,62.7029494 C108.44916,62.5641079 108.582877,62.4351837 108.582877,62.1773352 C108.582877,61.9591557 108.29068,61.7558521 107.973722,61.7558521 C107.795433,61.7558521 107.632001,61.8153556 107.547809,61.8748591 C107.453712,61.9442798 107.354662,62.0335351 107.121896,62.3310526 C107.092181,62.3707216 107.042656,62.3905561 106.998084,62.3905561 C106.963416,62.3905561 106.928749,62.3806388 106.903987,62.3608043 L106.25026,61.8649419 C106.185877,61.8153556 106.166067,61.7261004 106.21064,61.6566796 C106.636553,60.9475963 107.240755,60.6004926 108.048009,60.6004926 C108.899835,60.6004926 109.850711,61.2798242 109.850711,62.1872525 Z M108.582877,65.5194482 C108.582877,65.6087034 108.513542,65.6781242 108.424397,65.6781242 L107.473522,65.6781242 C107.384377,65.6781242 107.315042,65.6087034 107.315042,65.5194482 L107.315042,64.5673922 C107.315042,64.478137 107.384377,64.4087163 107.473522,64.4087163 L108.424397,64.4087163 C108.513542,64.4087163 108.582877,64.478137 108.582877,64.5673922 L108.582877,65.5194482 Z M84.6155392,67.9494933 C84.6155392,69.7787423 83.3654993,70.4767452 82.4520085,70.9942302 C81.8870867,71.3191625 81.5385178,71.9088547 81.5385178,72.1856489 C81.5385178,72.4022705 81.3702432,72.570754 81.1538902,72.570754 L78.8461241,72.570754 C78.6297711,72.570754 78.4614965,72.4022705 78.4614965,72.1856489 L78.4614965,71.7524057 C78.4614965,70.585056 79.6273991,69.5861898 80.4807917,69.2010847 C81.2139882,68.8641178 81.5385178,68.5512199 81.5385178,67.9254242 C81.5385178,67.3959047 80.8293606,66.9024889 80.0601052,66.9024889 C79.6273991,66.9024889 79.2307518,67.0469033 79.0264184,67.1913177 C78.7980457,67.3598011 78.5576534,67.5764227 77.9927315,68.2984947 C77.9206138,68.394771 77.8004177,68.4429091 77.6922411,68.4429091 C77.6081038,68.4429091 77.5239665,68.41884 77.4638685,68.3707019 L75.8772793,67.1672486 C75.7210243,67.0469033 75.6729459,66.8302817 75.7811224,66.6617982 C76.8148093,64.94086 78.2812023,64.0984426 80.2403994,64.0984426 C82.3077732,64.0984426 84.6155392,65.7471737 84.6155392,67.9494933 Z M81.5385178,76.0366996 C81.5385178,76.2533212 81.3702432,76.4218046 81.1538902,76.4218046 L78.8461241,76.4218046 C78.6297711,76.4218046 78.4614965,76.2533212 78.4614965,76.0366996 L78.4614965,73.7260692 C78.4614965,73.5094476 78.6297711,73.3409641 78.8461241,73.3409641 L81.1538902,73.3409641 C81.3702432,73.3409641 81.5385178,73.5094476 81.5385178,73.7260692 L81.5385178,76.0366996 Z M43.0930797,77.2864517 C43.0930797,79.8491049 41.3418589,80.8269595 40.0621207,81.5519206 C39.2707036,82.0071287 38.7823824,82.8332472 38.7823824,83.2210171 C38.7823824,83.5244892 38.5466412,83.7605231 38.2435453,83.7605231 L35.0105223,83.7605231 C34.7074264,83.7605231 34.4716852,83.5244892 34.4716852,83.2210171 L34.4716852,82.6140729 C34.4716852,80.9786955 36.1050353,79.579352 37.3005803,79.039846 C38.3277386,78.5677783 38.7823824,78.1294297 38.7823824,77.2527326 C38.7823824,76.5109119 37.7889014,75.8196699 36.7112271,75.8196699 C36.1050353,75.8196699 35.5493595,76.0219846 35.2631023,76.2242993 C34.9431677,76.4603332 34.6063945,76.7638053 33.8149774,77.7753789 C33.7139454,77.9102554 33.5455588,77.9776937 33.3940109,77.9776937 C33.2761403,77.9776937 33.1582696,77.9439745 33.0740763,77.8765363 L30.8513731,76.1905802 C30.6324705,76.0219846 30.5651158,75.7185125 30.7166638,75.4824787 C32.1647886,73.0715614 34.2191053,71.8913922 36.963807,71.8913922 C39.8600567,71.8913922 43.0930797,74.201152 43.0930797,77.2864517 Z M38.7823824,88.6160766 C38.7823824,88.9195487 38.5466412,89.1555826 38.2435453,89.1555826 L35.0105223,89.1555826 C34.7074264,89.1555826 34.4716852,88.9195487 34.4716852,88.6160766 L34.4716852,85.3790409 C34.4716852,85.0755688 34.7074264,84.839535 35.0105223,84.839535 L38.2435453,84.839535 C38.5466412,84.839535 38.7823824,85.0755688 38.7823824,85.3790409 L38.7823824,88.6160766 Z" id="tlp-users-waiting"></path>
                            </g>
                        </g>
                    </g>
                </svg>
                <span class="siteadmin-homepage-validation-count '.$pending_class.'">'.$pending_users.'</span>
            </div>
            '.$pending_action.'
        </section>
    ');
}

if (ForgeConfig::get(\ProjectManager::CONFIG_PROJECT_APPROVAL) == 1) {
    $groups_pending       = '<p class="siteadmin-homepage-no-validation">'.$Language->getText('admin_main', 'review_pending_projects_empty').'</p>';
    $groups_pending_class = '';

    if ($project_pending_count > 0) {
        $groups_pending       = '<a href="approve-pending.php" class="tlp-button-primary tlp-button-wide">' . $Language->getText('admin_main', 'review_pending_projects') . '</a>';
        $groups_pending_class = 'tlp-text-warning';
    }

    $wProject = new Widget_Static($Language->getText('admin_main', 'header_group'));
    $wProject->setAdditionalClass('siteadmin-homepage-projects');
    $wProject->setIcon('fa-archive');
    $wProject->setContent('
        <section class="tlp-pane-section">
            <div class="siteadmin-homepage-validation">
                <svg width="121px" height="75px" viewBox="0 0 121 75" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <g fill-rule="evenodd">
                        <path class="siteadmin-homepage-projects-icon" d="M78.2857265,34.2637155 L102.728863,34.2637155 C103.567308,34.2637155 104.26202,34.9584274 104.26202,35.7968728 L104.26202,38.7585748 L114.290471,38.7585748 C114.603075,38.7585748 114.86209,39.0175896 114.86209,39.3301936 L114.86209,41.616669 C114.86209,41.9292731 114.603075,42.1882878 114.290471,42.1882878 L104.26202,42.1882878 L104.26202,42.5852749 L113.693905,42.5852749 C114.000142,42.5852749 114.254951,42.8338467 114.265203,43.1378622 L120.559702,43.1378622 C120.72507,43.1378622 120.86209,43.2748818 120.86209,43.4402503 L120.86209,44.6498026 C120.86209,44.815171 120.72507,44.9521906 120.559702,44.9521906 L114.265524,44.9521906 L114.265524,45.162198 L120.244116,45.162198 C120.409485,45.162198 120.546505,45.2992175 120.546505,45.464586 L120.546505,50.0004071 C120.546505,50.1657756 120.409485,50.3027951 120.244116,50.3027951 L114.265524,50.3027951 L114.265524,51.7311763 C114.265524,52.0437804 114.006509,52.3027951 113.693905,52.3027951 L104.26202,52.3027951 L104.26202,58.7942323 C104.26202,59.6326777 103.567308,60.3273896 102.728863,60.3273896 L78.2857265,60.3273896 L78.2857265,71.1904844 C78.2857265,72.9059794 76.8643163,74.3273896 75.1488213,74.3273896 L6.13690527,74.3273896 C4.4214102,74.3273896 3,72.9059794 3,71.1904844 L3,24.1369053 C3,22.4214102 4.4214102,21 6.13690527,21 L75.1488213,21 C76.818464,21 78.2095325,22.3464418 78.2827017,24 L104.328933,24 C105.167378,24 105.86209,24.6947119 105.86209,25.5331573 L105.86209,31.6657865 C105.86209,32.5042319 105.167378,33.1989438 104.328933,33.1989438 L78.2857265,33.1989438 L78.2857265,34.2637155 Z M116.924649,47.202443 C116.256835,47.202443 115.715024,47.7442544 115.715024,48.4120685 C115.715024,49.0798826 116.256835,49.621694 116.924649,49.621694 C117.592463,49.621694 118.134275,49.0798826 118.134275,48.4120685 C118.134275,47.7442544 117.592463,47.202443 116.924649,47.202443 Z M117.125888,49.2247867 C117.125888,49.2530861 117.103878,49.2750967 117.075578,49.2750967 L116.773719,49.2750967 C116.745419,49.2750967 116.723409,49.2530861 116.723409,49.2247867 L116.723409,48.9229271 C116.723409,48.8946278 116.745419,48.8726172 116.773719,48.8726172 L117.075578,48.8726172 C117.103878,48.8726172 117.125888,48.8946278 117.125888,48.9229271 L117.125888,49.2247867 Z M117.243729,48.564758 C117.169702,48.6072839 117.124026,48.6844605 117.124026,48.7206863 C117.124026,48.7490369 117.101976,48.7710874 117.073625,48.7710874 L116.771219,48.7710874 C116.742868,48.7710874 116.720818,48.7490369 116.720818,48.7206863 L116.720818,48.6639851 C116.720818,48.5112069 116.873596,48.3804791 116.985423,48.330078 C117.0815,48.2859771 117.124026,48.2450263 117.124026,48.1631245 C117.124026,48.0938231 117.031099,48.0292467 116.930297,48.0292467 C116.873596,48.0292467 116.82162,48.0481471 116.794844,48.0670475 C116.764919,48.089098 116.733418,48.1174486 116.659391,48.2119506 C116.649941,48.2245508 116.634191,48.230851 116.620015,48.230851 C116.60899,48.230851 116.597965,48.2277009 116.59009,48.2214008 L116.382185,48.0638974 C116.36171,48.0481471 116.35541,48.0197965 116.369585,47.997746 C116.505038,47.7725163 116.697192,47.662264 116.953923,47.662264 C117.224828,47.662264 117.527235,47.8780435 117.527235,48.1662746 C117.527235,48.4056796 117.363431,48.4970316 117.243729,48.564758 Z M117.802634,46.3321585 C117.802634,46.497527 117.665614,46.6345466 117.500246,46.6345466 L116.290693,46.6345466 C116.125325,46.6345466 115.988305,46.497527 115.988305,46.3321585 C115.988305,46.16679 116.125325,46.0297704 116.290693,46.0297704 L117.500246,46.0297704 C117.665614,46.0297704 117.802634,46.16679 117.802634,46.3321585 Z M107.418955,46.442049 C106.156553,46.442049 105.132341,47.4662614 105.132341,48.7286628 C105.132341,49.9910642 106.156553,51.0152766 107.418955,51.0152766 C108.681356,51.0152766 109.705568,49.9910642 109.705568,48.7286628 C109.705568,47.4662614 108.681356,46.442049 107.418955,46.442049 Z M107.799367,50.2649836 C107.799367,50.3184792 107.757759,50.3600869 107.704263,50.3600869 L107.133643,50.3600869 C107.080148,50.3600869 107.03854,50.3184792 107.03854,50.2649836 L107.03854,49.6943637 C107.03854,49.6408681 107.080148,49.5992604 107.133643,49.5992604 L107.704263,49.5992604 C107.757759,49.5992604 107.799367,49.6408681 107.799367,49.6943637 L107.799367,50.2649836 Z M108.022126,49.0172992 C107.88219,49.097688 107.795847,49.2435787 107.795847,49.312058 C107.795847,49.3656505 107.754164,49.4073336 107.700571,49.4073336 L107.128918,49.4073336 C107.075325,49.4073336 107.033642,49.3656505 107.033642,49.312058 L107.033642,49.204873 C107.033642,48.9160689 107.322446,48.6689479 107.533839,48.5736723 C107.715458,48.4903062 107.795847,48.4128948 107.795847,48.258072 C107.795847,48.127068 107.620182,48.0049962 107.429631,48.0049962 C107.322446,48.0049962 107.224193,48.0407246 107.173578,48.0764529 C107.117008,48.118136 107.057461,48.1717285 106.917525,48.3503702 C106.899661,48.3741891 106.869887,48.3860985 106.843091,48.3860985 C106.822249,48.3860985 106.801408,48.3801438 106.786521,48.3682343 L106.393509,48.0704982 C106.354804,48.0407246 106.342894,47.987132 106.36969,47.945449 C106.625743,47.5196862 106.988982,47.3112709 107.474292,47.3112709 C107.986398,47.3112709 108.558051,47.7191695 108.558051,48.2640267 C108.558051,48.7165857 108.248406,48.8892727 108.022126,49.0172992 Z M109.078651,44.796908 C109.078651,45.1095121 108.819636,45.3685269 108.507032,45.3685269 L106.220557,45.3685269 C105.907953,45.3685269 105.648938,45.1095121 105.648938,44.796908 C105.648938,44.484304 105.907953,44.2252892 106.220557,44.2252892 L108.507032,44.2252892 C108.819636,44.2252892 109.078651,44.484304 109.078651,44.796908 Z M85.8986172,44.6080936 C82.5126898,44.6080936 79.7656166,47.3551668 79.7656166,50.7410942 C79.7656166,54.1270216 82.5126898,56.8740947 85.8986172,56.8740947 C89.2845446,56.8740947 92.0316177,54.1270216 92.0316177,50.7410942 C92.0316177,47.3551668 89.2845446,44.6080936 85.8986172,44.6080936 Z M86.9189322,54.8617095 C86.9189322,55.0051918 86.8073348,55.1167892 86.6638525,55.1167892 L85.1333745,55.1167892 C84.9898922,55.1167892 84.8782949,55.0051918 84.8782949,54.8617095 L84.8782949,53.3312315 C84.8782949,53.1877492 84.9898922,53.0761519 85.1333745,53.0761519 L86.6638525,53.0761519 C86.8073348,53.0761519 86.9189322,53.1877492 86.9189322,53.3312315 L86.9189322,54.8617095 Z M87.5164028,51.5152551 C87.141076,51.7308684 86.9094913,52.1221667 86.9094913,52.3058372 C86.9094913,52.4495794 86.7976918,52.5613789 86.6539496,52.5613789 L85.1206995,52.5613789 C84.9769573,52.5613789 84.8651578,52.4495794 84.8651578,52.3058372 L84.8651578,52.0183528 C84.8651578,51.2437421 85.6397686,50.5809308 86.2067517,50.3253891 C86.693878,50.1017902 86.9094913,49.8941625 86.9094913,49.4789073 C86.9094913,49.1275375 86.4383363,48.8001247 85.927253,48.8001247 C85.6397686,48.8001247 85.3762412,48.8959528 85.2404847,48.991781 C85.0887568,49.1035804 84.9290432,49.2473226 84.5537164,49.7264633 C84.5058023,49.7903487 84.4259455,49.8222914 84.3540744,49.8222914 C84.2981747,49.8222914 84.2422749,49.8063201 84.2023465,49.7743774 L83.1482371,48.9758096 C83.0444233,48.8959528 83.0124806,48.7522106 83.0843517,48.6404111 C83.7711199,47.4984592 84.7453726,46.9394617 86.0470381,46.9394617 C87.4205747,46.9394617 88.9538249,48.0334996 88.9538249,49.4948787 C88.9538249,50.7087017 88.1233144,51.171871 87.5164028,51.5152551 Z M90.3501429,40.195608 C90.3501429,41.0340534 89.655431,41.7287653 88.8169856,41.7287653 L82.6843564,41.7287653 C81.845911,41.7287653 81.1511991,41.0340534 81.1511991,40.195608 C81.1511991,39.3571626 81.845911,38.6624507 82.6843564,38.6624507 L88.8169856,38.6624507 C89.655431,38.6624507 90.3501429,39.3571626 90.3501429,40.195608 Z M40.71342,42.1650391 C33.785668,42.1650391 28.1650391,47.785668 28.1650391,54.71342 C28.1650391,61.6411719 33.785668,67.2618009 40.71342,67.2618009 C47.6411719,67.2618009 53.2618009,61.6411719 53.2618009,54.71342 C53.2618009,47.785668 47.6411719,42.1650391 40.71342,42.1650391 Z M42.801028,63.1443749 C42.801028,63.4379458 42.5726951,63.6662787 42.2791241,63.6662787 L39.1477009,63.6662787 C38.85413,63.6662787 38.625797,63.4379458 38.625797,63.1443749 L38.625797,60.0129516 C38.625797,59.7193807 38.85413,59.4910477 39.1477009,59.4910477 L42.2791241,59.4910477 C42.5726951,59.4910477 42.801028,59.7193807 42.801028,60.0129516 L42.801028,63.1443749 Z M44.0234785,56.2973864 C43.2555438,56.7385404 42.7817117,57.5391532 42.7817117,57.9149511 C42.7817117,58.2090538 42.5529651,58.4378003 42.2588625,58.4378003 L39.1217672,58.4378003 C38.8276646,58.4378003 38.598918,58.2090538 38.598918,57.9149511 L38.598918,57.3267457 C38.598918,55.7418591 40.1838047,54.385719 41.3438764,53.8628698 C42.3405577,53.4053767 42.7817117,52.9805617 42.7817117,52.1309318 C42.7817117,51.4120141 41.8177084,50.7421136 40.77201,50.7421136 C40.1838047,50.7421136 39.6446164,50.938182 39.3668528,51.1342505 C39.0564111,51.362997 38.7296303,51.6570997 37.9616956,52.6374419 C37.8636613,52.7681542 37.700271,52.8335104 37.5532196,52.8335104 C37.4388464,52.8335104 37.3244731,52.8008323 37.2427779,52.7354762 L35.0860249,51.1015724 C34.8736174,50.938182 34.8082613,50.6440793 34.9553126,50.4153328 C36.3604699,48.0788504 38.3538325,46.9351178 41.0170956,46.9351178 C43.8274101,46.9351178 46.9645053,49.1735659 46.9645053,52.1636098 C46.9645053,54.6471436 45.2652454,55.5948077 44.0234785,56.2973864 Z M49.8214316,31.5714316 C49.8214316,32.9776833 48.4000214,34.1428633 46.6845264,34.1428633 L34.1369053,34.1428633 C32.4214102,34.1428633 31,32.9776833 31,31.5714316 C31,30.16518 32.4214102,29 34.1369053,29 L46.6845264,29 C48.4000214,29 49.8214316,30.16518 49.8214316,31.5714316 Z M81.5595371,3.13690527 C81.5595371,1.4214102 80.1381269,0 78.4226318,0 L3.13690527,0 C1.4214102,0 0,1.4214102 0,3.13690527 L0,15.6845264 C0,17.4000214 1.4214102,18.8214316 3.13690527,18.8214316 L78.4226318,18.8214316 C80.1381269,18.8214316 81.5595371,17.4000214 81.5595371,15.6845264 L81.5595371,3.13690527 Z" id="Combined-Shape" fill="#000000"></path>
                    </g>
                </svg>
                <span class="siteadmin-homepage-validation-count '.$groups_pending_class.'">'.$pending_projects.'</span>
            </div>
            '.$groups_pending.'
        </section>
    ');
}

// Start output
$siteadmin = new \Tuleap\Admin\AdminPageRenderer();
$siteadmin->header($Language->getText('admin_main', 'title'));

global $feedback;
echo html_feedback_top($feedback);

echo site_admin_warnings($nb_users_by_status);

echo '<div id="siteadmin-homepage-container">';
echo '<div class="siteadmin-homepage-column">';

$display_user_approval_block    = $GLOBALS['sys_user_approval'] == 1 && $pending_users > 0;
$display_project_approval_block = ForgeConfig::get(\ProjectManager::CONFIG_PROJECT_APPROVAL) == 1 &&
    $project_pending_count > 0;

if ($display_user_approval_block || $display_project_approval_block) {
    echo '<div class="siteadmin-homepage-row">';

    if ($display_user_approval_block) {
        $wUser->display();
    }

    if ($display_project_approval_block) {
        $wProject->display();
    }

    echo '</div>';
}

echo '<div class="siteadmin-homepage-row">';
$user_stats->display();

$project_stats->display();
echo '</div>';

echo '</div>';

$system_events_pane_builder = new Tuleap\Admin\SystemEvents\HomepagePanePresenterBuilder(
    new SystemEventDao(),
    EventManager::instance(),
    SystemEventManager::instance()
);
$system_events_pane_renderer = TemplateRendererFactory::build()->getRenderer(
    ForgeConfig::get('codendi_dir') .'/src/templates/admin/system_events/'
);
$system_events_pane_renderer->renderToPage(
    Tuleap\Admin\SystemEvents\HomepagePanePresenter::TEMPLATE,
    $system_events_pane_builder->build()
);

echo '</div>';

$GLOBALS['HTML']->footer(array());

<?php
/**
 * 吃月饼大比拼
 *
 * 作者:迷失卍国度
 *
 * qq : 15595755
 */
defined('IN_IA') or exit('Access Denied');
include "model.php";
define('RES', '../addons/weisrc_money/template/');

class weisrc_moneyModuleSite extends WeModuleSite
{
    public $_appid = '';
    public $_appsecret = '';
    public $_accountlevel = '';
    public $_account = '';

    public $_weid = '';
    public $_fromuser = '';
    public $_nickname = '';
    public $_headimgurl = '';
    public $_activeid = 0;

    public $_auth2_openid = '';
    public $_auth2_nickname = '';
    public $_auth2_headimgurl = '';

    public $table_reply = 'weisrc_money_reply';
    public $table_fans = 'weisrc_money_fans';
    public $table_record = 'weisrc_money_record';
    public $table_award = 'weisrc_money_award';

    function __construct()
    {
        global $_W, $_GPC;
        $this->_weid = $_W['uniacid'];
        $this->_fromuser = $_W['fans']['from_user']; //debug
        if ($_SERVER['HTTP_HOST'] == '127.0.0.1:8888' || $_SERVER['HTTP_HOST'] == '192.168.1.102:8888') {
            $this->_fromuser = 'debug2';
        }

        $this->_auth2_openid = 'auth2_openid_' . $_W['uniacid'];
        $this->_auth2_nickname = 'auth2_nickname_' . $_W['uniacid'];
        $this->_auth2_headimgurl = 'auth2_headimgurl_' . $_W['uniacid'];

        $this->_appid = '';
        $this->_appsecret = '';
        $this->_accountlevel = $_W['account']['level']; //是否为高级号

        if (isset($_COOKIE[$this->_auth2_openid])) {
            $this->_fromuser = $_COOKIE[$this->_auth2_openid];
        }

        if ($this->_accountlevel < 4) {
            $setting = uni_setting($this->_weid);
            $oauth = $setting['oauth'];
            if (!empty($oauth) && !empty($oauth['account'])) {
                $this->_account = account_fetch($oauth['account']);
                $this->_appid = $this->_account['key'];
                $this->_appsecret = $this->_account['secret'];
            }
        } else {
            $this->_appid = $_W['account']['key'];
            $this->_appsecret = $_W['account']['secret'];
        }
    }

    public function doMobileindex()
    {
        global $_GPC, $_W;
        $weid = $this->_weid;
        $from_user = $this->_fromuser;

        $NEEDREGISTER = "0";
        $isend = "false";

        $id = intval($_GPC['id']);
        $this->_activeid = $id;
        if (empty($id)) {
            message('抱歉，参数错误！', '', 'error');
        }

        $method = 'index';
        $authurl = $_W['siteroot'] . 'app/' . $this->createMobileUrl($method, array('id' => $id), true) . '&authkey=1';
        $url = $_W['siteroot'] . 'app/' . $this->createMobileUrl($method, array('id' => $id), true);
        if (isset($_COOKIE[$this->_auth2_openid])) {
            $from_user = $_COOKIE[$this->_auth2_openid];
            $nickname = $_COOKIE[$this->_auth2_nickname];
            $headimgurl = $_COOKIE[$this->_auth2_headimgurl];
        } else {
            if ($this->_accountlevel == 3) {
                $userinfo = $this->setUserInfo();
                if (!empty($userinfo)) {
                    $from_user = $userinfo["openid"];
                    $nickname = $userinfo["nickname"];
                    $headimgurl = $userinfo["headimgurl"];
                }
            } else {
                if (isset($_GPC['code'])) {
                    $userinfo = $this->oauth2($authurl);
                    if (!empty($userinfo)) {
                        $from_user = $userinfo["openid"];
                        $nickname = $userinfo["nickname"];
                        $headimgurl = $userinfo["headimgurl"];
                    } else {
                        message('授权失败!');
                    }
                } else {
                    if (!empty($this->_appsecret)) {
                        $this->getCode($url);
                    }
                }
            }
        }

        $reply = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $id));
        if ($reply == false) {
            message('抱歉，活动不存在！', '', 'error');
        } else {
            if ($reply['starttime'] > TIMESTAMP) {
                message('活动未开始，请等待...');
            }
            if ($reply['endtime'] < TIMESTAMP) {
                $isend = "true";
            }
            if ($reply['status'] == 0) {
                message('活动暂停，请稍后...');
            }
        }

        $follow_url = $reply['follow_url'];
        if (empty($from_user)) {
            if (!empty($reply['follow_url'])) {
                header("location:$follow_url");
            } else {
                message('抱歉，粉丝不存在！', '', 'error');
            }
        }

        $sub = 0;
        if ($this->_accountlevel == 4) {
            $userinfo = $this->getUserInfo($from_user);
            if ($userinfo['subscribe'] == 1) {
                $sub = 1;
            }
        } else {
            if ($_W['fans']['follow'] == 1) {
                $sub = 1;
            }
        }

        if ($sub == 0) {
            if ($reply['isneedfollow'] == 1) {
                if (!empty($follow_url)) {
                    header("location:$follow_url");
                } else {
                    message("请先关注公众号再玩游戏");
                }
            }
        }

        $bg = !empty($reply['bg']) ? tomedia($reply['bg']) : "../addons/weisrc_money/template/mobile/money/images/bg.jpg";


        $gametime = $reply['gametime'];
        $gamelevel = $reply['gamelevel'];

        $number_times = intval($reply['number_times']);//总游戏次数
        $most_num_times = intval($reply['most_num_times']);//每天游戏次数

        $fans = pdo_fetch("SELECT * FROM " . tablename($this->table_fans) . " WHERE from_user=:from_user AND rid=:rid LIMIT 1", array(':from_user' => $from_user, ':rid' => $id));

        $usertotalnum = intval($fans['totalnum']);//用户总游戏次数
        $todaynum = intval($fans['todaynum']);//用户今日游戏次数
        $sharelotterynum = intval($fans['sharelotterynum']);//分享可游戏次数

        $nowtime = mktime(0, 0, 0);
        if ($fans['lasttime'] <= $nowtime) {
            $todaynum = 0;//今日次数重置为0
        }

        if ($number_times != 0) { //总游戏次数
            //用户所剩游戏次数
            $totalnum = $number_times - $usertotalnum + $sharelotterynum;
        }

        if ($most_num_times != 0) { //每天游戏次数
            //用户今日所剩游戏次数
            $todaynum = $most_num_times - $todaynum + $sharelotterynum;
        }

        $can_play_num = $todaynum;
        if ($todaynum > $totalnum) {
            $can_play_num = $totalnum;
        }

        if (empty($fans)) {
            $NEEDREGISTER = "1";
            $insert = array(
                'rid' => $id,
                'weid' => $weid,
                'from_user' => $from_user,
                'nickname' => $nickname,
                'headimgurl' => $headimgurl,
                'dateline' => TIMESTAMP
            );
            if (!empty($this->_account)) {
                if (!empty($nickname)) {
                    pdo_insert($this->table_fans, $insert);
                }
            } else {
                pdo_insert($this->table_fans, $insert);
            }
        } else {
            if (empty($fans['tel']) || empty($fans['username'])) {
                $NEEDREGISTER = "1";
            } else {
                $NEEDREGISTER = "0";
            }
            if (!empty($nickname) && !empty($headimgurl)) {
                pdo_update($this->table_fans, array('nickname' => $nickname, 'headimgurl' => $headimgurl), array('id' => $fans['id']));
            }
        }

        $Gameovertext = $reply['Gameovertext'];
        $tips1text = $reply['tips1text'];
        $tips2text = $reply['tips2text'];
        $tips3text = $reply['tips3text'];
        $signtext = $reply['signtext'];

        //分享信息
        $share_url = empty($reply['share_url']) ? $_W['siteroot'] . 'app/' . $this->createMobileUrl('index', array('id' => $id), true) : $reply['share_url'];
        $share_title = empty($reply['share_title']) ? '中秋月饼吃到爽，谁更快快快！' : $reply['share_title'];
        $share_desc = empty($reply['share_desc']) ? '吃月饼最快的那个是你吗？吃货与饕餮的终极对决，敢不敢来挑战~' : str_replace("\r\n", " ", $reply['share_desc']);
        $share_image = tomedia($reply['share_image']);
        include $this->template('money/index');
    }

    public function doMobileSubmit()
    {
        global $_GPC, $_W;
        $weid = $this->_weid;
        $from_user = $this->_fromuser;
        $id = intval($_GPC['id']);
        $score = floatval($_GPC['score']);//分数
        $num = floatval($_GPC['num']);//数量

        if (empty($id)) {
            $this->showMsg('抱歉，参数错误！');
        }

        if (empty($from_user)) {
            $this->showMsg('会话已过期，请从微信端发送关键字重新进入!');
        }

        $reply = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $id));

        if ($reply == false) {
            $this->showMsg('抱歉，活动不存在！');
        } else {
            if ($reply['starttime'] > TIMESTAMP) {
                $this->showMsg('活动未开始，请等待...');
            }
            if ($reply['endtime'] < TIMESTAMP) {
                $this->showMsg('抱歉，活动已经结束，下次再来吧！');
            }
            if ($reply['status'] == 0) {
                $this->showMsg('活动暂停，请稍后...');
            }
        }

        $number_times = intval($reply['number_times']);//总游戏次数
        $most_num_times = intval($reply['most_num_times']);//每天游戏次数

        $fans = pdo_fetch("SELECT * FROM " . tablename($this->table_fans) . " WHERE from_user=:from_user AND rid=:rid LIMIT 1", array(':from_user' => $from_user, ':rid' => $id));

        if (empty($fans)) {
            $this->showMsg('粉丝不存在！');
        } else if($fans['isblack'] == 1) {
            $this->showMsg('对不起，您在黑名单中！');
        }

        $gamecredit = intval($fans['credit']);
        if ($gamecredit != 0) {
            $gamecredit = $score > $gamecredit? $score : $gamecredit;
        } else {
            $gamecredit = $score;
        }

        $usertotalnum = intval($fans['totalnum']);//用户总游戏次数
        $sharelotterynum = intval($fans['sharelotterynum']);//分享可游戏次数
        $todaynum = intval($fans['todaynum']);//用户今日游戏次数
        $nowtime = mktime(0, 0, 0);
        if ($fans['lasttime'] <= $nowtime) {
            $todaynum = 0;//今日次数设置为0
        }

        if ($number_times != 0 && $usertotalnum >= $number_times && $sharelotterynum <= 0) { //总次数有限制  用户已抽奖次数>=总抽奖次数
            $this->showMsg('抱歉，您游戏次数用完了，请下次再来吧！');
        }

        if ($most_num_times != 0 && $todaynum >= $most_num_times && $sharelotterynum <= 0) { //今天次数有限制
            $this->showMsg('抱歉，您今日游戏次数用完了，请下次再来吧！');
        }

        if ($number_times == 0 || $number_times > $usertotalnum) {
            if ($most_num_times == 0 || $most_num_times > $todaynum) {
                $datafans = array(
                    'credit' => $gamecredit,
                    'totalnum' => $fans['totalnum'] + 1,
                    'todaynum' => $todaynum + 1,
                    'lasttime' => TIMESTAMP,
                );
                pdo_update($this->table_fans, $datafans, array('id' => $fans['id']));

                $datarecord = array(
                    'weid' => $weid,
                    'rid' => $id,
                    'fansid' => $fans['id'],
                    'credit' => $score,
                    'dateline' => TIMESTAMP
                );
                pdo_insert($this->table_record, $datarecord);

                $fanslist = pdo_fetchall("SELECT * FROM " . tablename($this->table_fans) . " WHERE rid=:rid and credit <> 0 ORDER BY credit LIMIT 10000", array(':rid' => $id));
                $mycredit = 0;
                $myrank = 0;
                foreach($fanslist as $key => $value) {
                    if ($value['from_user'] == $from_user) {
                        $myrank = $key + 1;
                        $mycredit = floatval($value['credit']);
                    }
                }

                $resultscore = $mycredit;
                if ($mycredit != 0) {
                    if ($score < $mycredit) {
                        $resultscore = $score;
                    }
                } else {
                    $resultscore = $score;
                }

                $result = array(
                    "status" => 1,
                    "rank" => $myrank,
                    "score" => $resultscore,
                    "prize" => false
                );
                echo json_encode($result);
                exit;
            }
        }

        if($sharelotterynum > 0) {
            $endsharenum = $sharelotterynum - 1;
            pdo_update($this->table_fans, array('sharelotterynum' => $endsharenum,'credit' => $gamecredit), array('id' => $fans['id']));
            $datarecord = array(
                'weid' => $weid,
                'rid' => $id,
                'fansid' => $fans['id'],
                'credit' => $score,
                'dateline' => TIMESTAMP
            );
            pdo_insert($this->table_record, $datarecord);
        } else {
            $this->showMsg('抱歉，您游戏次数用完了，请下次再来吧！');
        }

        $fanslist = pdo_fetchall("SELECT * FROM " . tablename($this->table_fans) . " WHERE rid=:rid and credit <> 0 ORDER BY credit LIMIT 10000", array(':rid' => $id));
        $mycredit = 0;
        $myrank = 0;
        foreach($fanslist as $key => $value) {
            if ($value['from_user'] == $from_user) {
                $myrank = $key + 1;
                $mycredit = floatval($value['credit']);
            }
        }

        $resultscore = $mycredit;
        if ($mycredit != 0) {
            if ($score < $mycredit) {
                $resultscore = $score;
            }
        } else {
            $resultscore = $score;
        }

        $result = array(
            "status" => 1,
            "rank" => $myrank,
            "score" => $resultscore,
            "prize" => false
        );
        echo json_encode($result);
        exit;
    }

    public function doMobileGetallrank()
    {
        global $_GPC, $_W;
        $weid = $this->_weid;
        $from_user = $this->_fromuser;
        $id = intval($_GPC['id']);

        $reply = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $id));
        if (empty($reply['showusernum'])) {
            $reply['showusernum'] = 10;
        }

        $list = pdo_fetchall("SELECT * FROM " . tablename($this->table_fans) . " WHERE rid = :rid AND credit<>0 AND isblack=0 ORDER BY credit DESC LIMIT " . $reply['showusernum'], array(':rid' => $id));

        foreach($list as $key => $value) {
            $rank[$key] = array('headimg' => tomedia($value['headimgurl']), 'username' => $value['nickname'], 'score' => $value['credit'], 'wxid' => $value['from_user']);
        }

        $self = pdo_fetch("SELECT c.* FROM (SELECT a.headimgurl as headimg,a.nickname AS username,a.credit AS score,a.from_user AS wxid,(@rowNum:=@rowNum+1) as rank  FROM " . tablename($this->table_fans) . " a,(Select (@rowNum :=0)) b WHERE a.rid = :rid AND a.credit<>0 AND a.isblack=0 ORDER BY credit DESC) c WHERE c.wxid=:from_user LIMIT 1", array(':rid' => $id, ':from_user' => $from_user));

        $topList = array('topList' => $rank);
        if (!empty($self)) {
            $topList['self'] = $self;
        }

        echo json_encode($topList);
    }

    public function doMobileSaveUserinfo()
    {
        global $_W, $_GPC;
        $weid = $this->_weid;
        $from_user = $this->_fromuser;
        $id = intval($_GPC['id']);
        $username = trim($_GPC['username']);
        $tel = trim($_GPC['tel']);

        if (empty($id)) {
            $this->showMsg('抱歉，参数错误！');
        }
        if (empty($from_user)) {
            $this->showMsg('会话已过期，请从微信端发送关键字重新进入!');
        }

        $reply = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $id));
        if ($reply == false) {
            $this->showMsg('抱歉，活动不存在！');
        }

        $fans = pdo_fetch("SELECT * FROM " . tablename($this->table_fans) . " WHERE from_user=:from_user AND rid=:rid LIMIT 1", array(':from_user' => $from_user, ':rid' => $id));

        if (empty($fans)) {
            $this->showMsg('粉丝不存在！');
        }

        $datafans = array(
            'username' => $username,
            'tel' => $tel
        );
        pdo_update($this->table_fans, $datafans, array('id' => $fans['id']));
        $this->showMsg('注册成功！', 1);
    }

    public function doMobileAutoSaveCredit()
    {
        global $_W, $_GPC;
        $weid = $this->_weid;
        $from_user = $this->_fromuser;
        $id = intval($_GPC['id']);
        $point = floatval($_GPC['score']);
        if (empty($id)) {
            $this->showMsg('抱歉，参数错误！');
        }

        if ($point > 500) {
            $this->showMsg('抱歉，参数错误！');
        }

        if (empty($from_user)) {
            $this->showMsg('会话已过期，请从微信端发送关键字重新进入!');
        }

        $reply = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $id));

        if ($reply == false) {
            $this->showMsg('抱歉，活动不存在！');
        } else {
            if ($reply['starttime'] > TIMESTAMP) {
                $this->showMsg('活动未开始，请等待...');
            }
            if ($reply['endtime'] < TIMESTAMP) {
                $this->showMsg('抱歉，活动已经结束，下次再来吧！');
            }
            if ($reply['status'] == 0) {
                $this->showMsg('活动暂停，请稍后...');
            }
        }

        $number_times = intval($reply['number_times']);//总游戏次数
        $most_num_times = intval($reply['most_num_times']);//每天游戏次数

        $fans = pdo_fetch("SELECT * FROM " . tablename($this->table_fans) . " WHERE from_user=:from_user AND rid=:rid LIMIT 1", array(':from_user' => $from_user, ':rid' => $id));

        if (empty($fans)) {
            $this->showMsg('粉丝不存在！');
        }

        $gamecredit = $fans['credit'];
        if ($point > $gamecredit) {
            $gamecredit = $point;
        }
        $totalgamecredit = $fans['totalcredit'] + $point;

        $usertotalnum = intval($fans['totalnum']);//用户总游戏次数
        $sharelotterynum = intval($fans['sharelotterynum']);//分享可游戏次数
        $todaynum = intval($fans['todaynum']);//用户今日游戏次数
        $nowtime = mktime(0, 0, 0);
        if ($fans['lasttime'] <= $nowtime) {
            $todaynum = 0;//今日次数设置为0
        }

        if ($number_times != 0 && $usertotalnum >= $number_times && $sharelotterynum <= 0) { //总次数有限制  用户已抽奖次数>=总抽奖次数
            $this->showMsg('抱歉，您游戏次数用完了，请下次再来吧！');
        }

        if ($most_num_times != 0 && $todaynum >= $most_num_times && $sharelotterynum <= 0) { //今天次数有限制
            $this->showMsg('抱歉，您今日游戏次数用完了，请下次再来吧！');
        }

        if ($number_times == 0 || $number_times > $usertotalnum) {
            if ($most_num_times == 0 || $most_num_times > $todaynum) {
                $datafans = array(
                    'credit' => $gamecredit,
                    'totalcredit' => $totalgamecredit,
                    'totalnum' => $fans['totalnum'] + 1,
                    'todaynum' => $todaynum + 1,
                    'lasttime' => TIMESTAMP,
                );
                pdo_update($this->table_fans, $datafans, array('id' => $fans['id']));
                $datarecord = array(
                    'weid' => $weid,
                    'rid' => $id,
                    'fansid' => $fans['id'],
                    'credit' => $point,
                    'dateline' => TIMESTAMP
                );
                pdo_insert($this->table_record, $datarecord);
                $this->showMsg('success', 1);
            }
        }

        if($sharelotterynum > 0) {
            $endsharenum = $sharelotterynum - 1;
            pdo_update($this->table_fans, array('sharelotterynum' => $endsharenum,'credit' => $gamecredit,
                'totalcredit' => $totalgamecredit), array('id' => $fans['id']));
            $datarecord = array(
                'weid' => $weid,
                'rid' => $id,
                'fansid' => $fans['id'],
                'credit' => $point,
                'dateline' => TIMESTAMP
            );
            pdo_insert($this->table_record, $datarecord);
            $this->showMsg('success', 1);
        } else {
            $this->showMsg('抱歉，您游戏次数用完了，请下次再来吧！');
        }
    }

    public function getItemTiles()
    {
        global $_W;
        $articles = pdo_fetchall("SELECT * FROM " . tablename('weisrc_money_reply') . " WHERE weid = '{$_W['uniacid']}'");
        if (!empty($articles)) {
            foreach ($articles as $row) {
                $urls[] = array('title' => $row['title'], 'url' => $this->createMobileUrl('index', array('id' => $row['rid']), true));
            }
            return $urls;
        }
    }


    public function doMobileShare()
    {
        global $_W, $_GPC;
        $weid = $this->_weid;
        $from_user = $this->_fromuser;
        $id = intval($_GPC['id']);

        $fans = pdo_fetch("SELECT * FROM " . tablename($this->table_fans) . " WHERE from_user=:from_user AND rid=:rid LIMIT 1", array(':from_user' => $from_user, ':rid' => $id));
        if (!empty($fans)) {
            $reply = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE weid=:weid AND rid=:rid LIMIT 1", array(':weid' => $weid, ':rid' => $id));
            if (empty($reply)) {
                $this->showMsg('感谢您的分享!', 1);
            }

            if ($reply['daysharenum'] <= 0 || $reply['sharelotterynum'] <= 0) {
                $this->showMsg('感谢您的分享!.', 1);
            }

            $daysharenum = $reply['daysharenum'];
            $lotterynum = $reply['sharelotterynum'];
            $data = array(
                'todaysharenum' => $fans['todaysharenum'] + 1,
                'sharenum' => $fans['sharenum'] + $lotterynum,
                'sharelotterynum' => $fans['sharelotterynum'] + $lotterynum,
                'lastsharetime' => TIMESTAMP
            );

            $nowtime = mktime(0, 0, 0);
            if ($fans['lastsharetime'] <= $nowtime) {
                $data['todaysharenum'] = 1;
            }

            if ($data['todaysharenum'] > $daysharenum) {
                $this->showMsg('感谢您的分享!!', 1);
            }

            if ($reply['sharelotterynum'] > 0) {
                pdo_update($this->table_fans, $data, array('id' => $fans['id']));
                $this->showMsg('感谢您的分享，您获得' . $lotterynum . '次游戏机会', 1);
            } else {
                $this->showMsg('感谢您的分享!!!', 1);
            }
        } else {
            $this->showMsg('粉丝不存在！');
        }
    }

    public function showMsg($msg, $success = 0) {
        $result = array(
            'msg' => $msg,
            'success' => $success
        );
        echo json_encode($result);
        exit;
    }

    public function doWebManage() {
        global $_GPC, $_W;
        load()->model('reply');
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $sql = "uniacid = :weid AND `module` = :module";
        $params = array();
        $params[':weid'] = $_W['uniacid'];
        $params[':module'] = 'weisrc_money';

        if (isset($_GPC['keywords'])) {
            $sql .= ' AND `name` LIKE :keywords';
            $params[':keywords'] = "%{$_GPC['keywords']}%";
        }

        $list = reply_search($sql, $params, $pindex, $psize, $total);
        $pager = pagination($total, $pindex, $psize);

        if (!empty($list)) {
            foreach ($list as &$item) {
                $condition = "`rid`={$item['id']}";
                $item['keywords'] = reply_keywords_search($condition);
                $weisrc_money = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE rid = :rid ", array(':rid' => $item['id']));
                $item['viewnum'] = $item['viewnum`'];
                $item['starttime'] = date('Y-m-d H:i', $weisrc_money['starttime']);
                $endtime = $weisrc_money['endtime'];
                $item['endtime'] = date('Y-m-d H:i', $endtime);
                $nowtime = time();
                if ($weisrc_money['starttime'] > $nowtime) {
                    $item['show'] = '<span class="label label-warning">未开始</span>';
                } elseif ($endtime < $nowtime) {
                    $item['show'] = '<span class="label label-default">已结束</span>';
                } else {
                    if ($weisrc_money['status'] == 1) {
                        $item['show'] = '<span class="label label-success">已开始</span>';
                    } else {
                        $item['show'] = '<span class="label label-default">已暂停</span>';
                    }
                }
                $item['status'] = $weisrc_money['status'];
                $item['weid'] = $weisrc_money['weid'];
            }
        }
        include $this->template('manage');
    }

    public function doWebdelete() {
        global $_GPC, $_W;
        $rid = intval($_GPC['rid']);
        $rule = pdo_fetch("SELECT id, module FROM " . tablename('rule') . " WHERE id = :id and uniacid=:weid", array(':id' => $rid, ':weid' => $_W['uniacid']));
        if (empty($rule)) {
            message('抱歉，要修改的规则不存在或是已经被删除！');
        }
        if (pdo_delete('rule', array('id' => $rid))) {
            pdo_delete('rule_keyword', array('rid' => $rid));
            //删除统计相关数据
            pdo_delete('stat_rule', array('rid' => $rid));
            pdo_delete('stat_keyword', array('rid' => $rid));
        }
        message('规则操作成功！', $this->createWebUrl('manage', array('op' => 'display')), 'success');
    }

    public function doWebdeleteAll() {
        global $_GPC, $_W;

        foreach ($_GPC['idArr'] as $k => $rid) {
            $rid = intval($rid);
            if ($rid == 0)
                continue;
            $rule = pdo_fetch("SELECT id, module FROM " . tablename('rule') . " WHERE id = :id and weid=:weid", array(':id' => $rid, ':weid' => $_W['uniacid']));
            if (empty($rule)) {
                $this->message('抱歉，要修改的规则不存在或是已经被删除！');
            }
            if (pdo_delete('rule', array('id' => $rid))) {
                pdo_delete('rule_keyword', array('rid' => $rid));
                //删除统计相关数据
                pdo_delete('stat_rule', array('rid' => $rid));
                pdo_delete('stat_keyword', array('rid' => $rid));
                //调用模块中的删除
                $module = WeUtility::createModule($rule['module']);
                if (method_exists($module, 'ruleDeleted')) {
                    $module->ruleDeleted($rid);
                }
            }
        }
        $this->message('规则操作成功！', '', 0);
    }

    public function doWebfanslist() {
        global $_GPC, $_W;
        load()->func('tpl');
        $weid = $this->_weid;
        $rid = intval($_GPC['rid']);

        if (empty($rid)) {
            message('抱歉，传递的参数错误！', '', 'error');
        }

        $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
        $url = $this->createWebUrl('fanslist', array('op' => 'display', 'rid' => $rid));

        if ($operation == 'display') {
            $reply = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
            $condition = ' credit ';
            if ($reply == false) {
                $this->showMsg('抱歉，活动不存在！');
            } else {
                if ($reply['mode'] == 1) {
                    $condition = ' totalcredit ';
                }
            }
            $pindex = max(1, intval($_GPC['page']));
            $psize = 12;

            if ($_GPC['out_put'] == 'output') {
                $list = pdo_fetchall("SELECT * FROM " . tablename($this->table_fans) . " WHERE rid = :rid AND isblack=0 ORDER BY {$condition} desc,id desc ", array(':rid' => $rid));

                $i = 0;
                foreach ($list as $key => $value) {
                    $arr[$i]['rank'] = $key + 1;
                    $arr[$i]['username'] = $value['username'];
                    $arr[$i]['tel'] = $value['tel'];
                    $arr[$i]['from_user'] = $value['from_user'];
                    $arr[$i]['nickname'] = $value['nickname'];
                    $arr[$i]['credit'] = $value['credit'];
                    $arr[$i]['dateline'] = date('Y-m-d H:i:s', $value['dateline']);
                    $i++;
                }
                $this->exportexcel($arr, array('排名', '姓名', '联系电话','微信ID', '昵称', '成绩', '参与时间'), time());
                exit();
            }

            $start = ($pindex - 1) * $psize;
            $limit = "";
            $limit .= " LIMIT {$start},{$psize}";
            $list = pdo_fetchall("SELECT * FROM " . tablename($this->table_fans) . " WHERE rid = :rid AND credit<>0 AND isblack=0 ORDER BY {$condition} desc,id DESC " . $limit, array(':rid' => $rid));

            $total = pdo_fetchcolumn("SELECT count(1) FROM " . tablename($this->table_fans) . " WHERE rid = :rid  ", array(':rid' => $rid));
            $gametotal = pdo_fetchcolumn("SELECT count(1) FROM " . tablename($this->table_fans) . " WHERE rid = :rid AND credit<>0 AND isblack=0 ", array(':rid' => $rid));
            $blacktotal = pdo_fetchcolumn("SELECT count(1) FROM " . tablename($this->table_fans) . " WHERE rid = :rid AND credit<>0 AND isblack=1 ", array(':rid' => $rid));
            $pager = pagination($gametotal, $pindex, $psize);
        } else if ($operation == 'post') {
            $id = intval($_GPC['id']);
            $item = pdo_fetch("SELECT * FROM " . tablename($this->table_fans) . " WHERE id = :id", array(':id' => $id));
            if (checksubmit()) {
                $data = array(
                    'weid' => $weid,
                    'rid' => $rid,
                    'nickname' => trim($_GPC['nickname']),
                    'username' => trim($_GPC['username']),
                    'tel' => trim($_GPC['tel']),
                    'credit' => floatval($_GPC['credit']),
                    'totalcredit' => floatval($_GPC['totalcredit']),
                    'dateline' => TIMESTAMP
                );
                if (!empty($_GPC['headimgurl'])) {
                    $data['headimgurl'] = $_GPC['headimgurl'];
                }

                if (empty($item)) {
                    pdo_insert($this->table_fans, $data);
                } else {
                    unset($data['dateline']);
                    pdo_update($this->table_fans, $data, array('id' => $id, 'weid' => $weid));
                }
                message('操作成功！', $url, 'success');
            }
        } else if ($operation == 'delete') {
            $id = intval($_GPC['id']);
            $item = pdo_fetch("SELECT id FROM " . tablename($this->table_fans) . " WHERE id = :id AND weid=:weid", array(':id' => $id, ':weid' => $weid));
            if (empty($item)) {
                message('抱歉，不存在或是已经被删除！', $url, 'error');
            }
            pdo_delete($this->table_fans, array('id' => $id, 'weid' => $weid));
            message('删除成功！', $url, 'success');
        } else if ($operation == 'displayblack') {
            $reply = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
            $condition = ' credit ';
            if ($reply == false) {
                $this->showMsg('抱歉，活动不存在！');
            } else {
                if ($reply['mode'] == 1) {
                    $condition = ' totalcredit ';
                }
            }
            $pindex = max(1, intval($_GPC['page']));
            $psize = 12;

            $start = ($pindex - 1) * $psize;
            $limit = "";
            $limit .= " LIMIT {$start},{$psize}";
            $list = pdo_fetchall("SELECT * FROM " . tablename($this->table_fans) . " WHERE rid = :rid AND credit<>0 AND isblack=1 ORDER BY {$condition} asc,id DESC " . $limit, array(':rid' => $rid));

            $total = pdo_fetchcolumn("SELECT count(1) FROM " . tablename($this->table_fans) . " WHERE rid = :rid  ", array(':rid' => $rid));
            $gametotal = pdo_fetchcolumn("SELECT count(1) FROM " . tablename($this->table_fans) . " WHERE rid = :rid AND credit<>0 AND isblack=1 ", array(':rid' => $rid));
            $pager = pagination($gametotal, $pindex, $psize);
        }  else if ($operation == 'blackcheck') {
            $id = intval($_GPC['id']);
            $black = intval($_GPC['black']);
            $item = pdo_fetch("SELECT id FROM " . tablename($this->table_fans) . " WHERE id = :id AND weid=:weid", array(':id' => $id, ':weid' => $weid));
            if (empty($item)) {
                message('抱歉，不存在或是已经被删除！', $url, 'error');
            }
            $url = $this->createWebUrl('fanslist', array('op' => 'displayblack', 'rid' => $rid));
            pdo_update($this->table_fans, array('isblack' => $black), array('id' => $id, 'weid' => $weid));
            message('操作成功！', $url, 'success');
        }
        include $this->template('fanslist');
    }

    protected function exportexcel($data = array(), $title = array(), $filename = 'report')
    {
        header("Content-type:application/octet-stream");
        header("Accept-Ranges:bytes");
        header("Content-type:application/vnd.ms-excel");
        header("Content-Disposition:attachment;filename=" . $filename . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        //导出xls 开始
        if (!empty($title)) {
            foreach ($title as $k => $v) {
                $title[$k] = iconv("UTF-8", "GB2312", $v);
            }
            $title = implode("\t", $title);
            echo "$title\n";
        }
        if (!empty($data)) {
            foreach ($data as $key => $val) {
                foreach ($val as $ck => $cv) {
                    $data[$key][$ck] = iconv("UTF-8", "GB2312", $cv);
                }
                $data[$key] = implode("\t", $data[$key]);

            }
            echo implode("\n", $data);
        }
    }

    public function doWebRecord() {
        global $_GPC, $_W;
        $weid = $this->_weid;
        $rid = intval($_GPC['rid']);
        $fansid = intval($_GPC['fansid']);

        if (empty($rid)) {
            message('抱歉，传递的参数错误！', '', 'error');
        }

        $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
        $url = $this->createWebUrl('Record', array('op' => 'display', 'rid' => $rid, 'fansid' => $fansid));

        if ($operation == 'display') {
            $pindex = max(1, intval($_GPC['page']));
            $psize = 12;
            $start = ($pindex - 1) * $psize;
            $limit = "";
            $limit .= " LIMIT {$start},{$psize}";
            $list = pdo_fetchall("SELECT * FROM " . tablename($this->table_record) . " WHERE rid = :rid AND fansid=:fansid ORDER BY id DESC " . $limit, array(':rid' => $rid, ':fansid' => $fansid));

            $total = pdo_fetchcolumn("SELECT count(1) FROM " . tablename($this->table_record) . " WHERE rid = :rid AND fansid=:fansid ", array(':rid' => $rid, ':fansid' => $fansid));
            $pager = pagination($total, $pindex, $psize);
        } else if ($operation == 'delete') {
            $id = intval($_GPC['id']);
            $item = pdo_fetch("SELECT id FROM " . tablename($this->table_record) . " WHERE id = :id", array(':id' => $id));
            if (empty($item)) {
                message('抱歉，不存在或是已经被删除！', $url, 'error');
            }
            pdo_delete($this->table_record, array('id' => $id, 'weid' => $weid));
            message('删除成功！', $url, 'success');
        }

        include $this->template('record');
    }

    public function doWebsetshow() {
        global $_GPC, $_W;
        $rid = intval($_GPC['rid']);
        $isstatus = intval($_GPC['status']);

        if (empty($rid)) {
            message('抱歉，传递的参数错误！', '', 'error');
        }
        $temp = pdo_update('weisrc_money_reply', array('status' => $isstatus), array('rid' => $rid));
        message('状态设置成功！', referer(), 'success');
    }

    public function doWebsetstatus() {
        global $_GPC, $_W;
        $id = intval($_GPC['id']);
        $status = intval($_GPC['status']);
        if (empty($id)) {
            message('抱歉，传递的参数错误！', '', 'error');
        }
        $data = array(
            'status' => 1,
            'usetime' => TIMESTAMP
        );

        $temp = pdo_update('weisrc_money_sncode', $data, array('id' => $id, 'weid' => $_W['uniacid']));
        if ($temp == false) {
            message('抱歉，刚才操作数据失败！', '', 'error');
        } else {
            message('状态设置成功！', $this->createWebUrl('sncodelist', array('rid' => $_GPC['rid'])), 'success');
        }
    }

    public function doWebgetphone() {
        global $_GPC, $_W;
        $rid = intval($_GPC['rid']);
        $fans = $_GPC['fans'];
        $tel = pdo_fetchcolumn("SELECT tel FROM " . tablename('weisrc_money_fans') . " WHERE rid = " . $rid . " and  from_user='" . $fans . "'");
        if ($tel == false) {
            echo '没有登记';
        } else {
            echo $tel;
        }
    }

    public function setUserInfo()
    {
        global $_GPC, $_W;
        $weid = $this->_weid;
        $from_user = $this->_fromuser;

        $userinfo = $this->getUserInfo($from_user);

        //设置cookie信息
        setcookie($this->_auth2_headimgurl, $userinfo['headimgurl'], time() + 3600 * 24);
        setcookie($this->_auth2_nickname, $userinfo['nickname'], time() + 3600 * 24);
        setcookie($this->_auth2_openid, $from_user, time() + 3600 * 24);
        setcookie($this->_auth2_sex, $userinfo['sex'], time() + 3600 * 24);
        return $userinfo;
    }

    public function oauth2($url)
    {
        global $_GPC, $_W;
        load()->func('communication');
        $code = $_GPC['code'];
        if (empty($code)) {
            message('code获取失败.');
        }
        $token = $this->getAuthorizationCode($code);
        $from_user = $token['openid'];
        $userinfo = $this->getUserInfo($from_user);
        $sub = 1;
        if ($userinfo['subscribe'] == 0) {
            //未关注用户通过网页授权access_token
            $sub = 0;
            $authkey = intval($_GPC['authkey']);
            if ($authkey == 0) {
                $oauth2_code = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $this->_appid . "&redirect_uri=" . urlencode($url) . "&response_type=code&scope=snsapi_userinfo&state=0#wechat_redirect";
                header("location:$oauth2_code");
            }
            $userinfo = $this->getUserInfo($from_user, $token['access_token']);
        }

        if (empty($userinfo) || !is_array($userinfo) || empty($userinfo['openid']) || empty($userinfo['nickname'])) {
            echo '<h1>获取微信公众号授权失败[无法取得userinfo], 请稍后重试！ 公众平台返回原始数据为: <br />' . $sub . $userinfo['meta'] . '<h1>';
            exit;
        }

        //设置cookie信息
        setcookie($this->_auth2_headimgurl, $userinfo['headimgurl'], time() + 3600 * 24);
        setcookie($this->_auth2_nickname, $userinfo['nickname'], time() + 3600 * 24);
        setcookie($this->_auth2_openid, $from_user, time() + 3600 * 24);
        setcookie($this->_auth2_sex, $userinfo['sex'], time() + 3600 * 24);
        return $userinfo;
    }

    public function getUserInfo($from_user, $ACCESS_TOKEN = '')
    {
        if ($ACCESS_TOKEN == '') {
            $ACCESS_TOKEN = $this->getAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$ACCESS_TOKEN}&openid={$from_user}&lang=zh_CN";
        } else {
            $url = "https://api.weixin.qq.com/sns/userinfo?access_token={$ACCESS_TOKEN}&openid={$from_user}&lang=zh_CN";
        }

        $json = ihttp_get($url);
        $userInfo = @json_decode($json['content'], true);
        return $userInfo;
    }

    public function getAuthorizationCode($code)
    {
        $oauth2_code = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->_appid}&secret={$this->_appsecret}&code={$code}&grant_type=authorization_code";
        $content = ihttp_get($oauth2_code);
        $token = @json_decode($content['content'], true);
        if (empty($token) || !is_array($token) || empty($token['access_token']) || empty($token['openid'])) {
            $id = $this->_activeid;
            $oauth2_code = $this->createMobileUrl('index', array('id' => $id), true);
            header("location:$oauth2_code");
//            echo '微信授权失败, 请稍后重试! 公众平台返回原始数据为: <br />' . $content['meta'] . '<h1>';
            exit;
        }
        return $token;
    }

    public function getAccessToken()
    {
        global $_W;
        $account = $_W['account'];
        if($this->_accountlevel < 4){
            if (!empty($this->_account)) {
                $account = $this->_account;
            }
        }
        load()->classs('weixin.account');
        $accObj= WeixinAccount::create($account['acid']);
        $access_token = $accObj->fetch_token();
        return $access_token;
    }

    public function getCode($url)
    {
        global $_W;
        $url = urlencode($url);
        $oauth2_code = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->_appid}&redirect_uri={$url}&response_type=code&scope=snsapi_base&state=0#wechat_redirect";
        header("location:$oauth2_code");
    }
}

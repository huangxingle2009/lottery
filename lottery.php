<?php
/**
 * 抽奖
 * User: huangbule
 * Date: 2017/4/25
 * Time: 14:12
 */

require __DIR__ . '/vendor/autoload.php';
session_start();

function select($openid)
{
    $file_name = 'user.json';
    if(file_exists($file_name))
    {
        $json_content = file_get_contents($file_name);
        $arr_content = json_decode($json_content, true);
        if (!$arr_content) {
            file_put_contents($file_name, json_encode([]));
            return true;
        }
        $arr_openid = array_column($arr_content, 'openid');
        if (!empty($arr_openid) && in_array($openid, $arr_openid)) {
            return false;
        }
    }
    return true;
}

function insert($data = [])
{
    $file_name = 'user.json';
    $json_content = file_get_contents($file_name);
    $arr_content = json_decode($json_content, true);
    $arr_content = array_merge($arr_content, $data);
    file_put_contents($file_name, json_encode($arr_content));
}


function get_rand($proArr)
{
    $result = '';

    //概率数组的总概率精度
    $proSum = array_sum($proArr);
    if ($proSum == 0) {
        return false;
    }
    //概率数组循环
    foreach ($proArr as $key => $proCur) {
        $randNum = mt_rand(1, $proSum);
        if ($randNum <= $proCur) {
            $result = $key;
            break;
        } else {
            $proSum -= $proCur;
        }
    }
    unset ($proArr);

    return $result;
}

function show_error() {
    echo json_encode(['code' => 300, 'data' => null]);
    exit;
}

$openid = isset($_SESSION['openid']) ? $_SESSION['openid'] : null;
if (!$openid) {
   show_error();
}

$lock = new lib\lock('key_name');
$lock->lock(); //加锁

//核对用户是否已经抽过奖了
if (!select($openid)) {
    $lock->unlock();
    show_error();
}

$prize_json = file_get_contents("prize.json");
$prize_arr = json_decode($prize_json, true);

$arr = [];

foreach ($prize_arr as $key => $val) {
    $arr[$val['id']] = $val['v'];
}

$rid = get_rand($arr); //根据概率获取奖项id

if (!$rid) {
    $lock->unlock();
    show_error();
}

$key = $rid - 1;

--$prize_arr[$key]['v'];

file_put_contents("prize.json", json_encode($prize_arr));

//插入用户抽奖信息
insert([$openid => [
    'openid' => $openid,
    'prize' => $prize_arr[$key]['prize'],
    'id' => $rid,
    'created_at' => date('Y-m-d H:i:s')
]]);

$lock->unlock();

echo json_encode(['code' => 200, 'data' => $key, 'msg' => $prize_arr[$key]['prize']]);
exit;


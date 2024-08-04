<?php

namespace xjryanse\approval\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\logic\Strings;
use xjryanse\logic\Arrays;
use think\Db;
/**
 * 提取审批人
 */
class ApprovalThingTplAuditerService extends Base implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\StaticModelTrait;
    
    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\approval\\model\\ApprovalThingTplAuditer';
    // 20230710：开启方法调用统计
    protected static $callStatics = true;
    
    public static function getAuditer($nodeKey,$data){
        $lists      = self::nodeKeyList($nodeKey);
        $info       = $lists[0];

        if(!$info){
            return '';
        }

        $condJson   = Strings::dataReplace($info['auditer_con'], $data);
        $condArr    = json_decode($condJson, JSON_UNESCAPED_UNICODE);
        if($info['auditer_table']){
//            dump($info['auditer_con']);
//            dump($data);
            // dump($condArr);
            $auditer    = self::dbAuditer($info['auditer_table'], $condArr, $info['auditer_field']);
        } else {
            $auditer = Arrays::value($data,$info['auditer_field']);
        }
        return $auditer;
    }
    
    public static function nodeKeyList($nodeKey){
        $con[] = ['node_key','=',$nodeKey];
        
        return self::staticConList($con);
    }
    /**
     * 提取审批人的查询条件
     * @param type $tableName   数据表
     * @param type $cond        查询条件
     * @param type $field       审批人字段
     * @return type
     */
    protected static function dbAuditer($tableName,$cond,$field){
        $auditer = Db::table($tableName)->where($cond)->value($field);
        return $auditer;
    }
    
    
}

<?php

namespace xjryanse\approval\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\logic\Arrays;
/**
 * 审批模板事项明细
 */
class ApprovalThingTplService extends Base implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\approval\\model\\ApprovalThingTpl';

    public static function extraDetails( $ids ){
        return self::commExtraDetails($ids, function($lists) use ($ids){
            $tplNodeCount   = ApprovalThingTplNodeService::groupBatchCount('tpl_id', $ids);
            $thingCount     = ApprovalThingService::groupBatchCount('tpl_id', $ids);

            foreach($lists as &$v){
                // 模板节点数量
                $v['tplNodeCount']      = Arrays::value($tplNodeCount, $v['id'], 0) ;
                // 已发起流程数量
                $v['thingCount']        = Arrays::value($thingCount, $v['id'], 0) ;
            }
            return $lists;
        });
    }
}

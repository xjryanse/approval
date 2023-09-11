<?php

namespace xjryanse\approval\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\system\service\SystemConditionService;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;

/**
 * 
 */
class ApprovalThingTplNodeService extends Base implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;
    use \xjryanse\traits\StaticModelTrait;
    
    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\approval\\model\\ApprovalThingTplNode';

    public static function extraDetails( $ids ){
        return self::commExtraDetails($ids, function($lists) use ($ids){
            $conCon[] = ['item_type','=','approval'];            
            $condCount     = SystemConditionService::groupBatchCount('item_key', array_column($lists,'node_key'), $conCon);
            // 审批人条件数
            $tplAuditerCount     = ApprovalThingTplAuditerService::groupBatchCount('node_key', array_column($lists,'node_key'));

            foreach($lists as &$v) {
                // 查询条件数量
                $v['condCount']         = Arrays::value($condCount, $v['node_key'], 0) ;
                // 审批人条件数
                $v['tplAuditerCount']   = Arrays::value($tplAuditerCount, $v['node_key'], 0) ;
                
            }
            return $lists;
        });
    }
    
    /**
     * 20230419:模板id
     * @param type $tplId
     */
    public static function listByTplId($tplId){
        $con[] = ['tpl_id','=',$tplId];
        $lists =  self::staticConList($con);
        
        return Arrays2d::sort($lists, 'level');
    }
    
    /**
     * 20230423:用于查询条件校验的key
     * 使用场景:流程节点推进
     * 1、当模板节点的key有特定查询条件时，使用此模板节点key
     * 2、当模板节点key无特定查询条件时，使用公共的条件key：APPR_COMM_COND
     */
    public function keyForCondition(){
        $info = $this->get();
        $con[] = ['item_key','=',$info['node_key']];
        $con[] = ['item_type','=','approval'];
        $conCount = SystemConditionService::staticConCount($con);
        return $conCount ? $info['node_key'] : 'APPR_COMM_COND';
    }

}

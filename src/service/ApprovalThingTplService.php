<?php

namespace xjryanse\approval\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\logic\Arrays;
use xjryanse\logic\Strings;
/**
 * 审批模板事项明细
 */
class ApprovalThingTplService extends Base implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;
    use \xjryanse\traits\StaticModelTrait;
    use \xjryanse\traits\KeyModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\approval\\model\\ApprovalThingTpl';
    // KeyModelTrait
    protected static $keyFieldName      = 'thing_cate';

    public static function extraDetails( $ids ){
        return self::commExtraDetails($ids, function($lists) use ($ids){
            $tplNodeCount       = ApprovalThingTplNodeService::groupBatchCount('tpl_id', $ids);
            $thingCount         = ApprovalThingService::groupBatchCount('tpl_id', $ids);
            $auditerConCount    = ApprovalThingTplAuditerService::groupBatchCount('node_key', array_column($lists,'node_key'));

            foreach($lists as &$v){
                // 模板节点数量
                $v['tplNodeCount']      = Arrays::value($tplNodeCount, $v['id'], 0) ;
                // 已发起流程数量
                $v['thingCount']        = Arrays::value($thingCount, $v['id'], 0) ;
                // 审核人条件数
                $v['auditerConCount']   = Arrays::value($auditerConCount, $v['node_key'], 0) ;
            }

            return $lists;
        });
    }
    /**
     * 20230425：生成审批事项名称
     * @param type $data
     * @return type
     */
    public function getThingName($data){
        $info = $this->get();
        $thingName = $info['thing_name'];
        
        return Strings::dataReplace($thingName, $data);
    }
    
    
}

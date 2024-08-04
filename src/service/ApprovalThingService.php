<?php

namespace xjryanse\approval\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\system\service\SystemAbilityGroupDeptService;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Arrays;
use xjryanse\logic\Strings;
use xjryanse\logic\DataCheck;
use Exception;
use think\facade\Request;
/**
 * 
 */
class ApprovalThingService extends Base implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\ObjectAttrTrait;
    use \xjryanse\traits\BelongTableModelTrait;
    
    //直接执行后续触发动作
    protected static $directAfter = true;
    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\approval\\model\\ApprovalThing';
    
    use \xjryanse\approval\service\thing\AuditerTraits;    
    use \xjryanse\approval\service\thing\TriggerTraits;    
    
    ///从ObjectAttrTrait中来
    // 定义对象的属性
    protected $objAttrs = [];
    // 定义对象是否查询过的属性
    protected $hasObjAttrQuery = [];
    // 定义对象属性的配置数组
    protected static $objAttrConf = [
        'approvalThingNode'=>[
            'class'     =>'\\xjryanse\\approval\\service\\ApprovalThingNodeService',
            'keyField'  =>'thing_id',
            'master'    =>true
        ]
    ];
    
    public static function extraDetails( $ids ){
        return self::commExtraDetails($ids, function($lists) use ($ids){
            self::objAttrsListBatch('approvalThingNode', $ids);            
            foreach($lists as &$v){
                // 模板节点数量
                $v['nodeCount']     = self::getInstance($v['id'])->objAttrsCount('approvalThingNode');
                // 模板节点明细
                // $v['nodeList']      = self::getInstance($v['id'])->objAttrsList('approvalThingNode');
                // 有额外进行数据拼接，如hasAudit
                $v['nodeList']      = ApprovalThingNodeService::thingNodeList($v['id']);
                // 当前审批字串
                $v['apprStr']       = ApprovalThingNodeService::calThingApprStr($v['id']);
                //末节点信息
                $lastNodeInfo               = $v['nodeList'] ? $v['nodeList'][count($v['nodeList']) -1] : [];
                // 末节点编号：
                $v['lastNodeId']            = Arrays::value($lastNodeInfo, 'id');
                // 末节点状态：待审批，同意，不同意
                $v['lastNodeAuditStatus']   = Arrays::value($lastNodeInfo, 'audit_status');
                // 末节点待审批人是我？
                $v['lastNodeAcceptUserIsMe']   = Arrays::value($lastNodeInfo, 'accept_user_id') == session(SESSION_USER_ID) ? 1 : 0;
            }
            return $lists;
        });
    }
    /**
     * 在审批阶段有判断
     * @return type
     */
    public function info() {
        //如果cache小于-1，表示外部没有传cache,取配置的cache值
        // $cache = $cache < 0 ? self::defaultCacheTime() : $cache;
        $info =  $this->commInfo();
        // $infoRaw       = $this->get();
        // $info       = $this->pushDynDataList($infoRaw);
        // 20240802:来源id
        // 20240802:有关联到审批判断，不行要挪到info
        $belongTableService = DbOperate::getService($info['belong_table']);
        if($belongTableService && $info['belong_table_id']){
            $belongInfo = $belongTableService::getInstance($info['belong_table_id'])->get();
            $info['belongTableInfo'] = $belongInfo;
        }
        return $info;
    }

    /**
     * 20230727:事项分类添加审批
     */
    public static function thingCateAddApprRam($thingCate,$userId, $data = []){
        $data['tpl_id']             = ApprovalThingTplService::keyToId($thingCate);
        if(!$data['tpl_id']){
            throw new Exception($thingCate.'审批模板未配置');
        }
        // $tplInfo = ApprovalThingTplService::getInstance($data['tpl_id'])->get();
        $data['thing_user_id']      = $userId;
        // 20230426:事项管理部门
        $customerId                 = Arrays::value($data, 'customer_id');
        $data['manage_dept_id']     = SystemAbilityGroupDeptService::customerAbilityGroupKeyGetManageDeptId($customerId, $thingCate);
        $data['thing_name']         = ApprovalThingTplService::getInstance($data['tpl_id'])->getThingName($data);
        // $data['belong_table']       = $tplInfo['belong_table'];
        // $data['belong_table_id']    = $data['id'];
        return self::saveGetIdRam($data);
    }
    /*
     * 20230426：更新事项的审核状态
     */
    public function updateAuditStatusRam(){
        // 【1】更新当前审批表
        $data['audit_status'] = ApprovalThingNodeService::calThingStatus($this->uuid);
        self::getInstance($this->uuid)->updateRam($data);
        // 【2】更新来源表审批状态
        $info = $this->get();
        $sourceService = DbOperate::getService($info['belong_table']);
        if (method_exists($sourceService, 'updateAuditStatusRam')) {
            $sourceService::getInstance($info['belong_table_id'])->updateAuditStatusRam();
        }
    }
    
    /**
     * 20230501：发起人视角，查询发起人的分页数据
     * @param type $con
     */
    public static function userThingPaginate($con, $order, $perPage, $having, $field, $withSum){
        // 提取当前用户的待审批数据
        $con[] = ['thing_user_id','=',session(SESSION_USER_ID)];
        return self::paginateX($con, $order, $perPage, $having, $field, $withSum);
    }
    /**
     * 20240415:审批链接
     * @describe 根据审批事项跳转不同链接
     */
    public function webAuditUrl(){
        $info   = $this->get();
        $tplId  = Arrays::value($info, 'tpl_id');

        $tplInfo = ApprovalThingTplService::getInstance($tplId)->get();
        $wAuditUrl = Arrays::value($tplInfo, 'w_audit_url');
        
        $url = Request::domain(true).'/wp/'.session(SESSION_COMPANY_KEY).Strings::dataReplace($wAuditUrl, $info);
        return $url;
    }
    
    /*
     * 20240718
     */
    public function doCancel(){
        $data['is_cancel']      = 1;
        $data['cancel_time']    = date('Y-m-d H:i:s');

        return $this->doUpdateRam($data);
    }
    
}

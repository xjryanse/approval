<?php

namespace xjryanse\approval\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\system\service\SystemAbilityGroupDeptService;
use xjryanse\system\service\SystemColumnService;
use xjryanse\system\service\SystemColumnListService;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Arrays;
use xjryanse\logic\DataCheck;
use Exception;
/**
 * 
 */
class ApprovalThingService extends Base implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;
    use \xjryanse\traits\ObjectAttrTrait;
    use \xjryanse\traits\BelongTableModelTrait;
    
    //直接执行后续触发动作
    protected static $directAfter = true;
    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\approval\\model\\ApprovalThing';

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

    public static function extraPreSave(&$data, $uuid) {
        self::stopUse(__METHOD__);
        // self::thingNodePush($uuid);
    }
    
    public static function extraPreUpdate(&$data, $uuid) {
        self::stopUse(__METHOD__);
        // self::thingNodePush($uuid);
    }
    
    public static function ramAfterSave(&$data, $uuid) {
        self::thingNodePushRam($uuid);
    }
    
    public static function ramAfterUpdate(&$data, $uuid) {
        // self::thingNodePushRam($uuid);
    }
    
    public function extraPreDelete(){
        self::checkTransaction();
        $info = $this->get();
        $rules['audit_status'][1] = '已审批通过，不可删';
        $rules['audit_status'][2] = '审批已完成，不可删';
        DataCheck::valueMatch($info, $rules);
        // 已审批通过，不可删除
        // 已审批不通过，不可删
        // 已有审批记录，不可删。
        

    }
    
    /*
     * 2030416：联动删除
     */
    public function extraAfterDelete($data){
        // 删节点
        ApprovalThingNodeService::uniDel('thing_id', $this->uuid);
        // 删抄送
        ApprovalThingCopytoService::uniDel('thing_id', $this->uuid);
        $belongTable = $data['belong_table'];
        if($belongTable){
            $service = DbOperate::getService($belongTable);
            // 清除来源表的审批事项编号字段
            $service::clearField('approval_thing_id',$this->uuid);
        }
    }
    /**
     * 20230419:推进事项流转
     */
//    public static function thingNodePush($id){
//        // return ApprovalThingNodeService::lastNodeFinishAndNext($id);
//        return ApprovalThingNodeService::lastNodeFinishAndNextRam($id);
//    }
    
    public static function thingNodePushRam($id){
        return ApprovalThingNodeService::lastNodeFinishAndNextRam($id);
    }
    
    /**
     * 20230425:事项分类添加审批
     */
//    public static function thingCateAddAppr($thingCate,$userId, $data = []){
//        $data['tpl_id']             = ApprovalThingTplService::keyToId($thingCate);
//        if(!$data['tpl_id']){
//            throw new Exception($thingCate.'审批模板未配置');
//        }
//        // $tplInfo = ApprovalThingTplService::getInstance($data['tpl_id'])->get();
//        $data['thing_user_id']      = $userId;
//        // 20230426:事项管理部门
//        $customerId                 = Arrays::value($data, 'customer_id');
//        $data['manage_dept_id']     = SystemAbilityGroupDeptService::customerAbilityGroupKeyGetManageDeptId($customerId, $thingCate);
//        $data['thing_name']         = ApprovalThingTplService::getInstance($data['tpl_id'])->getThingName($data);
//        // $data['belong_table']       = $tplInfo['belong_table'];
//        // $data['belong_table_id']    = $data['id'];
//
//        return self::saveGetId($data);
//    }
    
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
     * 20230501：审批人视角，查询审批人的分页数据
     * @param type $con
     */
    public static function auditerThingPaginate($con){
        // 提取当前用户的待审批数据
        $con[] = ['b.accept_user_id','=',session(SESSION_USER_ID)]; 
        $listRaw = self::mainModel()->alias('a')->join('w_approval_thing_node b','a.id = b.thing_id')
                ->where($con)->group('a.id')->field('a.id')->order('id desc')->paginate();
        $list = $listRaw ? $listRaw->toArray() : [];
        $data = self::extraDetails(array_column($list['data'],'id'));
        $list['data'] = $data;
        
        // 一定要放在setCustTable前面
        $columnId = SystemColumnService::tableNameGetId(self::getTable());
        $list['dynDataList'] = SystemColumnListService::getDynDataListByColumnIdAndData($columnId, $data);

        return $list;
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
    
}

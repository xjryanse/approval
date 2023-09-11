<?php

namespace xjryanse\approval\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\system\service\SystemConditionService;
use xjryanse\system\service\SystemCondService;
use xjryanse\system\service\SystemColumnListService;
use xjryanse\user\service\UserService;
use xjryanse\logic\Debug;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\wechat\service\WechatWePubTemplateMsgLogService;
use Exception;

/**
 * 
 */
class ApprovalThingNodeService extends Base implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;
    use \xjryanse\traits\ObjectAttrTrait;

    public static $lastNodeFinishCount = 0;   //末个节点执行次数
    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\approval\\model\\ApprovalThingNode';
    //直接执行后续触发动作
    protected static $directAfter = true;

    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function($lists) use ($ids) {
                    return self::extraDetailDeal($lists);
                });
    }

    /**
     * 20230502：数据列表处理
     * @param type $lists
     * @return type
     */
    protected static function extraDetailDeal($lists) {
        $userIds = array_merge(array_column($lists, 'accept_user_id'), array_column($lists, 'audit_user_id'));
        // 20230730
        $userTable = UserService::getTable();
        $userArr = SystemColumnListService::sDynDataQuery($userTable, 'id', $userIds, '*');
        //订单模板消息
        $wechatWePubTemplateMsgLogCount = WechatWePubTemplateMsgLogService::groupBatchCount('from_table_id', array_column($lists, 'id'));

        foreach ($lists as &$v) {
            // 是否已审批
            $v['hasAudit']          = Arrays::value($v,'audit_status') ? 1 : 0;
            // 是否填写了审批意见
            $v['hasAuditReason']    = Arrays::value($v,'audit_reason') ? 1 : 0;
            // 是否可撤回，控制显示
            $v['canRollBack']       = self::getInstance($v['id'])->canRollBack() ? 1 : 0;
            $acceptUserId           = Arrays::value($v,'accept_user_id','');
            // 收件人姓名
            $v['acceptUserName']    = isset($userArr[$acceptUserId]) ? Arrays::value($userArr[$acceptUserId], 'realname') : '';
            $auditUserId            = Arrays::value($v,'audit_user_id','');
            // 审批人姓名
            $v['auditUserName']     = isset($userArr[$auditUserId]) ? Arrays::value($userArr[$auditUserId], 'realname') : '';
            // 模板消息数
            $v['wechatWePubTemplateMsgLogCount'] = Arrays::value($wechatWePubTemplateMsgLogCount, $v['id'], 0);
        }
        return $lists;
    }

    /**
     * 20230419:审批事项的节点
     * @param type $thingId
     */
    public static function thingNodeList($thingId) {
        $lists = ApprovalThingService::getInstance($thingId)->objAttrsList('approvalThingNode');
        return self::extraDetailDeal($lists);
    }

    // 20210920 进一步梳理业务逻辑
    // 获取订单的需判断节点：如果条件达成：
    //  ---是末个节点，末个节点设为完成；获取下一个节点；
    //  ---非末个节点，末个节点设为关闭；将当前完成节点添加写入，获取下一个节点，
    //  ---如果下一个节点唯一，则写入待处理
//    public static function lastNodeFinishAndNext($thingId) {
//        // self::addFlow($thingId, 'TEST', '这是一个测试节点');
//        // 20230419：
//        // 提取全部节点；
//        // 按优先级循环判断
//        // ①获取需校验判断的节点
//        $nextCheckNodes = self::nextCheckNodes($thingId);
//        Debug::debug('$nextCheckNodes', $nextCheckNodes);
//        if (!$nextCheckNodes) {
//            return false;
//        }
//
//        // 20220618是否递归的最外层循环
//        // $isMain = self::$lastNodeFinishCount == 0;
//        self::nodeTimesPlusAndCheck();
//
//        foreach ($nextCheckNodes as &$tplNode) {
//            // 校验模板节点是否可添加
//            if (self::canTplNodeAdd($thingId, $tplNode['id'])) {
//                // 添加模板节点
//                self::addFlowByTplId($thingId, $tplNode['id']);
//            }
//        }
//    }

    // 20210920 进一步梳理业务逻辑
    // 获取订单的需判断节点：如果条件达成：
    //  ---是末个节点，末个节点设为完成；获取下一个节点；
    //  ---非末个节点，末个节点设为关闭；将当前完成节点添加写入，获取下一个节点，
    //  ---如果下一个节点唯一，则写入待处理
    public static function lastNodeFinishAndNextRam($thingId) {
        // self::addFlow($thingId, 'TEST', '这是一个测试节点');
        // 20230419：
        // 提取全部节点；
        // 按优先级循环判断
        // ①获取需校验判断的节点
        $nextCheckNodes = self::nextCheckNodes($thingId);
        Debug::debug('$nextCheckNodes', $nextCheckNodes);
        if (!$nextCheckNodes) {
            return false;
        }
        // 20220618是否递归的最外层循环
        // $isMain = self::$lastNodeFinishCount == 0;
        self::nodeTimesPlusAndCheck();
        foreach ($nextCheckNodes as &$tplNode) {
//            dump($tplNode);
//            dump('能添加吗？');
            // 校验模板节点是否可添加
            if (self::canTplNodeAdd($thingId, $tplNode['id'])) {
                // dump('可以添加');
                // 添加模板节点
                self::addFlowByTplIdRam($thingId, $tplNode['id']);
            }
        }
    }
    
    /**
     * 校验循环次数，避免死循环
     * @throws Exception
     */
    protected static function nodeTimesPlusAndCheck() {
        // ②防止死循环
        self::$lastNodeFinishCount = self::$lastNodeFinishCount + 1;
        // 20220312;因为检票，从20调到200；TODO检票的更优方案呢？？
        $limitTimes = 20;
        if (self::$lastNodeFinishCount > $limitTimes) {
            throw new Exception('lastNodeFinishAndNext 死循环，请联系开发' . $limitTimes);
        }
    }

    /**
     * 用于条件查询的参数
     * @param type $thingId
     * @return type
     * @throws Exception
     */
    protected static function apprThingCheckParam($thingId) {
        $param = ApprovalThingService::getInstance($thingId)->info(0);
        if (!$param) {
            throw new Exception('事项信息不存在 ' . $thingId);
        }
        $param['thingId'] = $thingId;
        return $param;
    }

    /**
     * 用于条件校验的参数
     * 使用场景：节点审批自动通过
     */
    protected static function apprNodeCheckParam($nodeId) {
        $info = self::getInstance($nodeId)->get();
        $thingId = $info['thing_id'];
        // 【1】提取审批事项的参数
        $param = self::apprThingCheckParam($thingId);
        // 【2】拼接当前节点的参数
        $param['nodeId'] = $nodeId;
        $param['node_key'] = $info['node_key'];
        $param['accept_user_id']    = Arrays::value($info, 'accept_user_id');
        $param['audit_status']      = Arrays::value($info, 'audit_status');
        $param['audit_user_id']     = Arrays::value($info, 'audit_user_id');
        $param['direction']         = Arrays::value($info, 'direction');
        return $param;
    }

    /**
     * 审批事项 + 模板节点拼接校验参数
     * 使用场景：节点的推进
     * @param type $thingId     
     * @param type $tplNodeId
     */
    protected static function apprTplNodeCheckParam($thingId, $tplNodeId) {
        // 【1】提取审批事项的参数
        $param = self::apprThingCheckParam($thingId);
        // 【2】拼接模板节点参数
        $tplNode = ApprovalThingTplNodeService::getInstance($tplNodeId)->get();        
        $nodeList = ApprovalThingService::getInstance($thingId)->objAttrsList('approvalThingNode');
        // 已有节点数
        $param['nodeCount'] = count($nodeList);
        // 优先级在当前节点之前
        $conPre = [];
        $conPre[] = ['level', '<', $tplNode['level']];
        $preNodeList = Arrays2d::listFilter($nodeList, $conPre);
        $param['preNodeCount'] = count($preNodeList);
        $param['thisCheckNodeKey'] = $tplNode['node_key'];
        // 当前添加节点优先级
        $param['thisNodeLevel'] = $tplNode['level'];
        return $param;
    }

    /**
     * 20210920获取下一个待校验节点：
     * 包含不在已有节点中的开始节点；和当前未完成的末节点
     */
    public static function nextCheckNodes($thingId) {
        // 20230419:当前事项已有节点
        $thingNodes = self::thingNodeList($thingId);
        // TODO:后向审批流程
        //提取level第一档；
        $thingInfo = ApprovalThingService::getInstance($thingId)->get();
        // dump($thingInfo);
        $tplNodeLists = ApprovalThingTplNodeService::listByTplId($thingInfo['tpl_id']);
        // dump($thingNodes);
        // 提取未解析的节点
        foreach ($tplNodeLists as $k => $v) {
            foreach ($thingNodes as $vv) {
                if ($vv['node_key'] == $v['node_key']) {
                    unset($tplNodeLists[$k]);
                }
            }
        }

        return $tplNodeLists;
    }

    /**
     * 计算事项的审批状态：20230426：
     */
    public static function calThingStatus($thingId) {
        $thingNodes = ApprovalThingService::getInstance($thingId)->objAttrsList('approvalThingNode');
        // 0待审批(尚无人批);1已通过；2不通过；3审批中(有人批，但还没批完)；4已撤销
        // dump($thingNodes);
        $con0[] = ['audit_status', '=', 0];
        if (count(Arrays2d::listFilter($thingNodes, $con0)) == count($thingNodes)) {
            //待审批：所有审批节点未审批
            return 0;
        }

        $con1[] = ['audit_status', '=', 1];
        if (count(Arrays2d::listFilter($thingNodes, $con1)) == count($thingNodes)) {
            //已通过：所有节点通过
            return 1;
        }

        $con2[] = ['audit_status', '=', 2];
        if (count(Arrays2d::listFilter($thingNodes, $con2))) {
            //不通过：有节点不通过
            return 2;
        }

        $con3[] = ['audit_status', '=', 0];
        if (count(Arrays2d::listFilter($thingNodes, $con3))) {
            //不通过：有节点待审批
            return 3;
        }
        //已撤销：TODO

        return '测试中';
    }

    /**
     * 
     * @param type $thingId
     * @return type
     */
    public static function calThingApprStr($thingId) {
        $thingNodes = ApprovalThingService::getInstance($thingId)->objAttrsList('approvalThingNode');
        $last = array_pop($thingNodes);
        //提取实际审批人
        $auditUserId            = Arrays::value($last,'audit_user_id','');
        $auditer = $auditUserId ? UserService::getInstance($auditUserId)->fRealname() : '';
        if ($last['audit_status'] == 1) {
            return $auditer . '审批通过';
        }
        if ($last['audit_status'] == 2) {
            return $auditer . '审批不通过';
        }
        if ($last['audit_status'] == 0) {
            // 提取收件人
            $auditer = UserService::getInstance($last['accept_user_id'])->fRealname();
            return '等待' . $auditer . '审批';
        }
    }

    /**
     * 根据订单模板id添加流程
     * @param type $orderId     订单id
     * @param type $tplId       模板id
     */
//    protected static function addFlowByTplId($thingId, $tplNodeId, $data = []) {
//        $tplNodeInfo = ApprovalThingTplNodeService::getInstance($tplNodeId)->get();
//        $data['tpl_node_id'] = $tplNodeId;
//        $data['node_cate'] = $tplNodeInfo['node_cate'];
//        $data['direction'] = $tplNodeInfo['direction'];
//        $data['level'] = $tplNodeInfo['level'];
//
//        return self::addFlow($thingId, $tplNodeInfo['node_key'], $tplNodeInfo['node_name'], $data);
//    }
    
        /**
     * 根据订单模板id添加流程
     * @param type $orderId     订单id
     * @param type $tplId       模板id
     */
    protected static function addFlowByTplIdRam($thingId, $tplNodeId, $data = []) {
        $tplNodeInfo = ApprovalThingTplNodeService::getInstance($tplNodeId)->get();
        $data['tpl_node_id'] = $tplNodeId;
        $data['node_cate'] = $tplNodeInfo['node_cate'];
        $data['direction'] = $tplNodeInfo['direction'];
        $data['level'] = $tplNodeInfo['level'];

        return self::addFlowRam($thingId, $tplNodeInfo['node_key'], $tplNodeInfo['node_name'], $data);
    }

    /**
     * 给订单添加流程【参数有优化】
     * @param type $orderId
     * @param type $nodeKey
     * @param type $nodeName
     * @param type $operateRole
     * @param type $flowStatus
     * @param array $data
     * @return type
     */
//    public static function addFlow($thingId, $nodeKey, $nodeName, array $data = []) {
//        $data['id'] = self::mainModel()->newId();
//        //订单id
//        $data['thing_id'] = $thingId;
//        //节点key
//        $data['node_key'] = $nodeKey;
//        //节点名称
//        $data['node_name'] = $nodeName;
//        //订单信息
//        $thingInfo = ApprovalThingService::getInstance($thingId)->get();
//        $thingInfo['thingId'] = $thingId;
//
//        $data['company_id'] = Arrays::value($thingInfo, 'company_id');
//        // 待审核
//        $data['audit_status'] = Arrays::value($data, 'audit_status', 0);
//        // 20230423：收件人逻辑
//        $data['accept_user_id'] = ApprovalThingTplAuditerService::getAuditer($nodeKey, $thingInfo);
//
//        $res = self::save($data);
//
//        return $res;
//    }

    /**
     * 给订单添加流程【参数有优化】
     * @param type $orderId
     * @param type $nodeKey
     * @param type $nodeName
     * @param type $operateRole
     * @param type $flowStatus
     * @param array $data
     * @return type
     */
    public static function addFlowRam($thingId, $nodeKey, $nodeName, array $data = []) {
        $data['id'] = self::mainModel()->newId();
        //订单id
        $data['thing_id'] = $thingId;
        //节点key
        $data['node_key'] = $nodeKey;
        //节点名称
        $data['node_name'] = $nodeName;
        //订单信息
        $thingInfo = ApprovalThingService::getInstance($thingId)->get();
        $thingInfo['thingId'] = $thingId;

        $data['company_id'] = Arrays::value($thingInfo, 'company_id');
        // 待审核
        $data['audit_status'] = Arrays::value($data, 'audit_status', 0);
        // 20230423：收件人逻辑
        $data['accept_user_id'] = ApprovalThingTplAuditerService::getAuditer($nodeKey, $thingInfo);

        $res = self::saveRam($data);

        return $res;
    }
    
    // 20230419：提交审批意见
    public static function auditOperateRam($id, $param) {
        if (!Arrays::value($param, 'audit_status')) {
            throw new Exception('审批状态必须');
        }
        $data['audit_status'] = Arrays::value($param, 'audit_status');
        $data['audit_reason'] = Arrays::value($param, 'audit_reason');
        $data['audit_time'] = date('Y-m-d H:i:s');
        $data['audit_user_id'] = session(SESSION_USER_ID);

        $res = self::getInstance($id)->updateRam($data);

        $nodeInfo = self::getInstance($id)->get();
        self::lastNodeFinishAndNextRam($nodeInfo['thing_id']);

        return $res;
    }

    /*
     * 20230419:取消审核
     */

    protected function auditCancel() {
        $data['audit_status'] = 0;
        $data['audit_reason'] = '';
        $data['audit_time'] = null;
        $data['audit_user_id'] = null;

        return $this->updateRam($data);
    }

    /**
     * 20230423：撤回审核（）；
     * 只有当下一步还未审核时，才能撤回
     * @param type $id
     */
    public static function auditRollback($id) {
        // 回滚条件判断
        if (!self::getInstance($id)->canRollBack()) {
            throw new Exception('该节点不可撤回，请联系开发');
        }

        $nextList = self::getInstance($id)->nextNodesList();
        // 删除后续节点
        $cond[] = ['audit_status', '=', 0];
        $cond[] = ['id', 'in', array_column($nextList, 'id')];
        self::where($cond)->delete();
        // 当前节点恢复为待审核
        return self::getInstance($id)->auditCancel();
    }

    /**
     * 20230423：节点是否可撤回
     */
    protected function canRollBack() {
        $info = $this->get();
        //待审批的节点，不需要撤回
        if ($info['audit_status'] == 0) {
            return false;
        }
        // 提取当前节点的后续全部节点
        $nextList = $this->nextNodesList();

        foreach ($nextList as $v) {
            // 后续有已批节点，则不可撤回
            if ($v['audit_status']) {
                return false;
            }
        }
        return true;
    }

    /**
     * 20230426:是否可自动放行
     */
    protected function canAutoPass() {
        $param = self::apprNodeCheckParam($this->uuid);
        // 20230423:节点key
        $autoPassCheckKey = $this->keyForAutoPass();
        // 校验是否达成
        // $isReached = SystemConditionService::isReachByItemKey('approval', $autoPassCheckKey, $param);
        // dump(__METHOD__);        
        $isReached = SystemCondService::isReachByItemKey('approval', $autoPassCheckKey,$this->uuid, $param);
        // dump($isReached);exit;
        return $isReached;
    }

    /**
     * 是否可添加该模板节点
     * 使用场景：流程节点推进
     */
    protected static function canTplNodeAdd($thingId, $tplNodeId) {
        // 条件校验参数
        $param = self::apprTplNodeCheckParam($thingId, $tplNodeId);
        // 20230423:节点key
        $condCheckKey = ApprovalThingTplNodeService::getInstance($tplNodeId)->keyForCondition();
        // 校验是否达成
        // $isReached = SystemConditionService::isReachByItemKey('approval', $condCheckKey, $param);
        // 20230729:替换
        $isReached = SystemCondService::isReachByItemKey('approval', $condCheckKey,$thingId, $param);
        return $isReached;
    }

    /**
     * 提取当前节点的后续节点
     * 使用场景：审核撤回
     * @return boolean
     */
    protected function nextNodesList() {
        $info = $this->get();
        // 提取事项的全部节点
        $nodeList = ApprovalThingService::getInstance($info['thing_id'])->objAttrsList('approvalThingNode');
        // 提取当前节点的后续
        $con[] = ['id', '>', $this->uuid];
        $con[] = ['level', '>', $info['level']];
        $nextList = Arrays2d::listFilter($nodeList, $con);
        return $nextList;
    }

    /**
     * 20230729
     * @param type $data
     * @param type $uuid
     */
    public static function extraPreSave(&$data, $uuid) {
        self::stopUse(__METHOD__);
    }

    public static function ramAfterSave(&$data, $uuid) {
        //订单追加节点（内存中追加）
        $thingId = Arrays::value($data, 'thing_id');
        //20220617：已经封装，可以注释
        //self::getInstance($uuid)->setUuData($data,true);
        ApprovalThingService::getInstance($thingId)->objAttrsPush('approvalThingNode', $data);
        //更新事项审批状态
        ApprovalThingService::getInstance($thingId)->updateAuditStatusRam();
        //【自动通过】
        if (self::getInstance($uuid)->canAutoPass()) {
            $param['audit_status'] = 1;
            $param['audit_reason'] = '自动通过';
            self::auditOperateRam($uuid, $param);
        }

        return $data;
    }

    public static function extraPreUpdate(&$data, $uuid) {
        self::stopUse(__METHOD__);
    }
    
    public static function ramPreUpdate(&$data, $uuid) {
        // self::checkTransaction();
        //订单更新节点（内存中追加）
        $info = self::getInstance($uuid)->get(0);
        $thingId = Arrays::value($info, 'thing_id');
        //为了只更新传入的$data，故放在preUpdate;
        //OrderService::getInstance($orderId)->updateFlowNode($uuid, $data);
        ApprovalThingService::getInstance($thingId)->objAttrsUpdate('approvalThingNode', $uuid, $data);

        return $data;
    }

    public static function ramAfterUpdate(&$data, $uuid) {
        // self::checkTransaction();
        //订单更新节点（内存中追加）
        $info = self::getInstance($uuid)->get(0);
        $thingId = Arrays::value($info, 'thing_id');
        // 递归判断是否可添加下节点
        self::lastNodeFinishAndNextRam($thingId);
        //更新事项审批状态
        // dump('更新');
        // dump($data);
        ApprovalThingService::getInstance($thingId)->updateAuditStatusRam();

        return $data;
    }

    public function extraPreDelete() {
        self::stopUse(__METHOD__);
    }

    public function ramPreDelete() {
        $info = $this->get();
        Debug::debug('info', $info);
        if ($info['audit_status']) {
            throw new Exception('已审批不可删');
        }
    }

    /**
     * 20230426：用于自动通过条件的校验key
     * 使用场景：节点自动通过审批
     * TODO:暂时使用通用的key
     */
    public function keyForAutoPass() {
        return 'APPR_AUTOPASS';
    }

}

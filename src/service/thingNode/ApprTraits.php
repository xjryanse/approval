<?php

namespace xjryanse\approval\service\thingNode;

use xjryanse\logic\Arrays;
use xjryanse\approval\service\ApprovalThingService;
use xjryanse\approval\service\ApprovalThingTplAuditerService;
use xjryanse\user\service\UserService;

/**
 * 审批逻辑
 */
trait ApprTraits{
    
    public static function apprPreGet($param){
        $id         = Arrays::value($param, 'id');
        // 20240803:虚拟写入，用于条件判断
        // self::getInstance($id)->setUuData(['audit_status'=>1]);

        $info       = self::getInstance($id)->get();
        $thingId    = Arrays::value($info, 'thing_id');

        // 包含nextAuditUserId；nextNodeKey数据
        $nextAuditPlan              = self::thingNextAuditPlan($thingId);

        $isNodeNeedPass             = Arrays::value($nextAuditPlan, 'isNodeNeedPass');
        // 20240802:自动带出下一级审批人
        $param['nextAuditUserId']   = $isNodeNeedPass ? Arrays::value($nextAuditPlan, 'nextAuditUserId') : '';
        $param['thing_id']          = $thingId;
        $param['$nextAuditPlan']          = $nextAuditPlan;        

        return $param;
    }
    /**
     * 下一个计划的审批数据
     */
    public static function thingNextAuditPlan($thingId){
        // $thingId     = Arrays::value($array, $key);
        $nextNodes      = self::nextCheckNodes($thingId);
        $nodeArr        = array_values($nextNodes);
        $nextNode       = $nodeArr ? $nodeArr[0] : [];
        $nodeKey        = $nextNode ? $nextNode['node_key'] : '';

        $thingInfo = ApprovalThingService::getInstance($thingId)->get();
        $thingInfo['thingId']   = $thingId;
        $auditUserId            = ApprovalThingTplAuditerService::getAuditer($nodeKey, $thingInfo);
        // 20240802:自动带出下一级审批人

        $param['nextAuditUserId']   = $auditUserId;
        $param['nextNodeKey']       = $nodeKey;
        $param['thing_id']          = $thingId;

        $param['isNodeNeedPass']    = self::isNodeNeedPass($thingId, $nextNode['id']) ? 1:0;

        return $param;
    }
    
}

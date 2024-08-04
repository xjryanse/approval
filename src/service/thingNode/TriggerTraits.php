<?php

namespace xjryanse\approval\service\thingNode;

use xjryanse\approval\service\ApprovalThingService;
use xjryanse\user\service\UserService;
use xjryanse\logic\Debug;
use xjryanse\logic\Arrays;
use Exception;
/**
 * 
 */
trait TriggerTraits{

    /**
     * 20230729
     * @param type $data
     * @param type $uuid
     */
    public static function extraPreSave(&$data, $uuid) {
        self::stopUse(__METHOD__);
    }
    
    public static function ramPreSave(&$data, $uuid) {
        if(!Arrays::value($data, 'node_name')){
            $nextAuditUserId = Arrays::value($data, 'accept_user_id');
            $realName = UserService::getInstance($nextAuditUserId)->fRealname();
            $data['node_name'] = $realName.'审批';
        }
        if(!Arrays::value($data, 'audit_status')){
            $data['audit_status'] = 0;
        }
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
            $param['audit_reason'] = '审批人是发起人自动通过';
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
        $info       = self::getInstance($uuid)->get(0);
        $thingId    = Arrays::value($info, 'thing_id');
        //为了只更新传入的$data，故放在preUpdate;
        //OrderService::getInstance($orderId)->updateFlowNode($uuid, $data);
        ApprovalThingService::getInstance($thingId)->objAttrsUpdate('approvalThingNode', $uuid, $data);

        return $data;
    }

    public static function ramAfterUpdate(&$data, $uuid) {
        // self::checkTransaction();
        //订单更新节点（内存中追加）
        $info       = self::getInstance($uuid)->get(0);
        $thingId    = Arrays::value($info, 'thing_id');
        // 递归判断是否可添加下节点
        // 20240803:加nextAuditUserId
        $nextAuditUserId = Arrays::value($data, 'nextAuditUserId');
        self::lastNodeFinishAndNextRam($thingId, $nextAuditUserId);
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
    
    
}

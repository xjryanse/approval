<?php

namespace xjryanse\approval\service\thingNode;

use xjryanse\approval\service\ApprovalThingService;
use xjryanse\user\service\UserService;
use xjryanse\logic\Arrays;

/**
 * 审批逻辑
 */
trait CalTraits{

    
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
    
}

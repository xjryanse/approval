<?php

namespace xjryanse\approval\service\thing;

use xjryanse\approval\service\ApprovalThingNodeService;
use xjryanse\approval\service\ApprovalThingCopytoService;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Arrays;
use xjryanse\logic\DataCheck;
/**
 * 审批人视角
 */
trait TriggerTraits{
    
    public static function extraPreSave(&$data, $uuid) {
        self::stopUse(__METHOD__);
        // self::thingNodePush($uuid);
    }
    
    public static function extraPreUpdate(&$data, $uuid) {
        self::stopUse(__METHOD__);
        // self::thingNodePush($uuid);
    }
    
    public static function ramAfterSave(&$data, $uuid) {
        // 20240407:增加
        $nextAuditUserId = Arrays::value($data, 'nextAuditUserId') ? : '';
        self::thingNodePushRam($uuid, $nextAuditUserId);
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
     * 20240731:节点推动
     * @param type $id
     * @param type $nextAuditUserId
     * @return type
     */
    public static function thingNodePushRam($id, $nextAuditUserId){
        return ApprovalThingNodeService::lastNodeFinishAndNextRam($id, $nextAuditUserId);
    }

    
}

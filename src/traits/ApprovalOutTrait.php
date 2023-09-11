<?php
namespace xjryanse\approval\traits;

use xjryanse\approval\service\ApprovalThingService;
use xjryanse\logic\Arrays;
use Exception;
/**
 * 审批外部表服务类复用库
 * 例如：用车申请；报销申请
 */
trait ApprovalOutTrait
{
    /**
     * 计算审批状态
     * @param bool $needAppr
     * @param string $apprThingId
     */
    protected static function calAuditStatus(int $needAppr, $apprThingId) {
        if (!$needAppr) {
            // 不需要审批，默认通过
            return 1;
        }
        $thing = ApprovalThingService::getInstance($apprThingId)->get();
        return Arrays::value($thing,'audit_status',0);
    }

    /**
     * 20230430:更新审批状态
     * @return type
     */
    public function updateAuditStatusRam() {
        $info = $this->get();
        if (!$info) {
            return false;
        }
        if(!isset($info['need_appr'])){
            return false;
            // throw new Exception(self::getTable().'的need_appr字段未配置，请联系开发');
        }
        $data['audit_status'] = self::calAuditStatus(intval($info['need_appr']), $info['approval_thing_id']);
        return $this->updateRam($data);
    }
}

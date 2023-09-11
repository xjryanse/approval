<?php
namespace xjryanse\approval\interfaces;

/**
 * 审批外接表实现接口
 */
interface ApprovalOutInterface
{
    /**
     * 20230704:将当前记录写入审批表
     * @param type $data
     */
    public function approvalAdd();
    /**
     * 20230704:根据审批单，更新审批状态
     */
    public function updateAuditStatusRam();

}

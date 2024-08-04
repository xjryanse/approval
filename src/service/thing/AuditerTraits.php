<?php

namespace xjryanse\approval\service\thing;

use xjryanse\system\service\SystemColumnService;
use xjryanse\system\service\SystemColumnListService;
use xjryanse\approval\service\ApprovalThingNodeService;
use think\facade\Request;
use xjryanse\logic\Arrays;
/**
 * 审批人视角
 */
trait AuditerTraits{
    /**
     * 20230501：审批人视角，查询审批人的分页数据
     * @param type $con
     */
    public static function auditerThingPaginate($con){
        $tNodeTable = ApprovalThingNodeService::getTable();
        // 提取当前用户的待审批数据
        $myStateCon = self::myStateCon();
        if($myStateCon){
            $con = array_merge($con, $myStateCon);
        }

        $con[] = ['b.accept_user_id','=',session(SESSION_USER_ID)]; 
//        $listRaw = self::mainModel()->alias('a')->join($tNodeTable.' b','a.id = b.thing_id')
//                ->where($con)->group('a.id')->field('a.id')->order('id desc')->paginate();
        // 需要取流程节点中有当前用户的全部id；
        // 还需要取各审批事项的最新一个节点。
        
        $lastNodeSql = ApprovalThingNodeService::mainModel()->sqlGroupMaxRecord('thing_id');
        // 20240516:只留最新节点
        $listRaw = self::mainModel()->alias('a')
                // 加where，取跟我有关的
                ->join($tNodeTable.' b','a.id = b.thing_id')
                // 取各项的末节点
                ->join($lastNodeSql.' c','a.id = c.thing_id') 
                ->where($con)
                ->field('a.id')->group('a.id')->order('id desc')->paginate();
        
        $list = $listRaw ? $listRaw->toArray() : [];
        $data = self::extraDetails(array_column($list['data'],'id'));
        $list['data'] = $data;
        
        // 一定要放在setCustTable前面
        $columnId = SystemColumnService::tableNameGetId(self::getTable());
        $list['dynDataList'] = SystemColumnListService::getDynDataListByColumnIdAndData($columnId, $data);

        $list['$sql'] = ApprovalThingNodeService::mainModel()->sqlGroupMaxRecord('thing_id');
        return $list;
    }
    
    /**
     * 获取当前用户状态参数
     * 待我审批：audit_status是3;accept_user_id是我；
     * 进行中：audit_status是3;accept_user_id不是我；
     * 已通过：audit_status是1；
     * 不通过：audit_status是2；
     */
    protected static function myStateCon(){
        $param = Request::param('table_data') ? : Request::param();
        $myState = Arrays::value($param, 'myState');
        if(!$myState){
            return [];
        }
        //  提取当前用户的状态参数：
        // 待我审批
        $cov['waitMe']  = [['a.is_cancel','=',0],['a.audit_status','in',[0,3]],['c.accept_user_id','=',session(SESSION_USER_ID)]];
        // 进行中
        $cov['doing']   = [['a.is_cancel','=',0],['a.audit_status','=',3],['c.accept_user_id','<>',session(SESSION_USER_ID)]];
        // 已通过
        $cov['pass']    = [['a.is_cancel','=',0],['a.audit_status','=',1]];
        // 不通过
        $cov['reject']  = [['a.is_cancel','=',0],['a.audit_status','=',2]];
        // 20240724:已取消
        $cov['cancel']  = [['a.is_cancel','=',1]];
        
        return Arrays::value($cov, $myState) ? : [];
    }
    
    
    
}

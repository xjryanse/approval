<?php

namespace xjryanse\approval\service;

use xjryanse\system\interfaces\MainModelInterface;

/**
 * 
 */
class ApprovalThingTplService extends Base implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\approval\\model\\ApprovalThingCopyto';

}

<?php

namespace xjryanse\approval\service;

use xjryanse\system\interfaces\MainModelInterface;

/**
 * 
 */
class ApprovalThingCopytoService extends Base implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\approval\\model\\ApprovalThingCopyto';

}

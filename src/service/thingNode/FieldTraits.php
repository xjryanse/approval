<?php

namespace xjryanse\approval\service\thingNode;

/**
 * 
 */
trait FieldTraits{

    /**
     *
     */
    public function fThingId() {
        return $this->getFFieldValue(__FUNCTION__);
    }
}

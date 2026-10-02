<?php
/**
 * Minimal stand-ins for the core event classes the plugin's observers
 * type-hint. Only objectid is needed. Kept in its own file because
 * namespaced declarations cannot share a file with global-namespace code.
 */
namespace core\event;

class base {
    public int $objectid;
    public function __construct(int $objectid) {
        $this->objectid = $objectid;
    }
}
class course_created extends base {}
class course_deleted extends base {}
class user_deleted   extends base {}
class group_deleted  extends base {}

<?php

namespace App\Models\Traits;

/** For critical application-owned models: do not commit a mutation without its observer's audit row. */
trait TransactionalActivityAudit
{
    public function save(array $options = [])
    {
        if (! config('activity_audit.enabled') || $this->getConnection()->transactionLevel() > 0) {
            return parent::save($options);
        }
        return $this->getConnection()->transaction(fn () => parent::save($options));
    }

    public function delete()
    {
        if (! config('activity_audit.enabled') || $this->getConnection()->transactionLevel() > 0) {
            return parent::delete();
        }
        return $this->getConnection()->transaction(fn () => parent::delete());
    }
}

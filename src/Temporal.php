<?php

namespace NavJobs\Temporal;

use Carbon\Carbon;
use NavJobs\Temporal\Exceptions\InvalidDateRangeException;

trait Temporal
{
    /**
     * Boot the temporal trait for a model.
     *
     * @return void
     */
    public static function bootTemporal()
    {
        static::registerEvents();
    }

    /**
     * Register the events for a Temporal Model.
     */
    public static function registerEvents()
    {
        static::creating(function ($item) {
            $item->startCannotBeInThePast();
            $item->endCurrent();
            $item->removeScheduled();
        });

        static::saving(function ($item) {
            $item->startCannotBeAfterEnd();
        });

        static::updating(function ($item) {
            return $item->preventUpdating();
        });

        static::deleting(function ($item) {
            return $item->endOrDelete();
        });
    }

    /********************************************************************************
     * Methods
     ********************************************************************************/

    /**
     * Checks if the model is valid.
     *
     * @param Carbon $validTime
     * @return mixed
     */
    public function isValid(Carbon $validTime = null)
    {
        $dateTime = $validTime ?? new Carbon();

        return $this->valid_start->lte($dateTime) && (is_null($this->valid_end) || $this->valid_end->gte($dateTime));
    }

    /**
     * Valid date cannot be in the past.
     *
     * @throws InvalidDateRangeException
     */
    protected function startCannotBeInThePast()
    {
        if ($this->valid_start < new Carbon()) {
            throw new InvalidDateRangeException;
        }
    }

    /**
     * Start date cannot be greater than end date.
     *
     * @throws InvalidDateRangeException
     */
    protected function startCannotBeAfterEnd()
    {
        if ($this->valid_end && $this->valid_start > $this->valid_end) {
            throw new InvalidDateRangeException;
        }
    }

    /**
     * If a temporal model is created, then we want any Temporal Models that were
     * already scheduled to start to be removed.
     */
    protected function removeScheduled()
    {
        $this->getQuery()->where('valid_start', '>', new Carbon())->delete();
    }

    /**
     * If a valid Temporal Model exists that should be ended go ahead and do so.
     */
    protected function endCurrent()
    {
        $currentItem = $this->getQuery()->valid()->first();

        if ($currentItem && $this->shouldBeEnded($currentItem)) {
            $currentItem->update([
                'valid_end' => $this->valid_start
            ]);
        }
    }

    /**
     * Build a query on the Temporal Model based on the fields that are present.
     *
     * @return mixed
     */
    private function getQuery()
    {
        $query = $this->where($this->temporalParentColumn, $this->{$this->temporalParentColumn});

        if ($this->temporalPolymorphicTypeColumn) {
            $query->where($this->temporalPolymorphicTypeColumn, $this->{$this->temporalPolymorphicTypeColumn});
        }

        return $query;
    }

    /**
     * Determine if the provided Temporal Model should be ended based the valid_start.
     *
     * @param $currentItem
     * @return bool
     */
    private function shouldBeEnded($currentItem)
    {
        return is_null($currentItem->valid_end) || $currentItem->valid_end > $this->valid_start;
    }

    /**
     * Only the valid_end attribute is eligible to be updated.
     */
    protected function preventUpdating()
    {
        return array_key_exists('valid_end', $this->getDirty()) && count($this->getDirty()) == 1;
    }

    /**
     * Only delete the Temporal model if valid_start is in the future, otherwise set the valid_end.
     *
     * @return bool|null
     */
    protected function endOrDelete()
    {
        if ($this->valid_start > Carbon::now()) {
            return true;
        }

        if ($this->isValid()) {
            $this->update([
                'valid_end' => Carbon::now()
            ]);
        }

        return false;
    }

    /********************************************************************************
     * Scopes
     ********************************************************************************/

    /**
     * Scope a valid temporal model.
     *
     * @param $query
     * @param Carbon $validTime
     * @return mixed
     */
    public function scopeValid($query, Carbon $validTime = null)
    {
        $dateTime = $validTime ?? new Carbon();

        return $query->where('valid_start', '<=', $dateTime)
            ->where(function ($query) use ($dateTime) {
                $query->whereNull('valid_end')
                    ->orWhere('valid_end', '>', $dateTime);
            });
    }

    /**
     * Scope a invalid temporal model.
     *
     * @param $query
     * @param Carbon $validTime
     * @return mixed
     */
    public function scopeInvalid($query, Carbon $validTime = null)
    {
        $dateTime = $validTime ?? new Carbon();

        return $query->where(function ($query) use ($dateTime) {
            $query->where('valid_start', '>', $dateTime)
                ->orWhere('valid_end', '<', $dateTime);
        });
    }
}
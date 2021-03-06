<?php

namespace Bkwld\Decoy\Observers;

use Event;
use Route;
use Config;
use Bkwld\Decoy\Models;
use Bkwld\Decoy\Models\Change;

/**
 * Create a log of all model changing events
 */
class Changes
{
    /**
     * Only log the following events
     *
     * @param array
     */
    protected $supported = ['created', 'updated', 'deleted'];

    /**
     * Handle all Eloquent model events
     *
     * @param Bkwld\Decoy\Models\Base $model
     */
    public function handle($model)
    {
        // Don't log the Change model events
        if (is_a($model, Models\Change::class)) {
            return;
        }

        // Don't log encoding or Image events since they aren't really the result of
        // admin input
        if (is_a($model, Models\Encoding::class) || is_a($model, Models\Image::class)) {
            return;
        }

        // Hide Elements.  To do this right, I should aggregate a bunch of Element
        // changes into a single log.
        if (is_a($model, Models\Element::class)) {
            return;
        }

        // Don't log changes to pivot models.  Even though a user may have initiated
        // this, it's kind of meaningless to them.  These events can happen when a
        // user messes with drag and drop positioning.
        if (is_a($model, \Illuminate\Database\Eloquent\Relations\Pivot::class)) {
            return;
        }

        // Don't log an admin logging in or out
        if (Route::is('decoy::account@postLogin', 'decoy::account@logout')) {
            return;
        }

        // Get the action of the event
        preg_match('#eloquent\.(\w+)#', Event::firing(), $matches);
        $action = $matches[1];
        if (!in_array($action, $this->supported)) {
            return;
        }

        // Get the admin acting on the record
        $admin = app('decoy.user');

        // If `log_changes` was configed as a callable, see if this model event
        // should not be logged
        if ($check = Config::get('decoy.site.log_changes')) {
            if (is_bool($check) && !$check) {
                return;
            }
            if (is_callable($check) && !call_user_func($check, $model, $action, $admin)) {
                return;
            }
        } else {
            return;
        }

        // Log the event
        Change::log($model, $action, $admin);
    }
}

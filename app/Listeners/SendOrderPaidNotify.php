<?php

namespace App\Listeners;

use App\Events\OrderPaidEvent;
use App\Exceptions\InvalidOrderStatusException;
use App\Exceptions\NotifyFailedException;
use App\Models\App;
use App\Models\Charge;
use App\Payment\Notify;
use App\Types\OrderStatus;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderPaidNotify
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param OrderPaidEvent $event
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(OrderPaidEvent $event)
    {
        /** @var Charge $charge */
        $charge = Charge::findOrFail($event->chargeId);

        if ($charge->{Charge::STATUS} !== OrderStatus::PAID) {
            throw new InvalidOrderStatusException();
        }

        $app = $charge->app;
        $notify = new Notify();
        try {
            $data = $charge->buildNotifyData();

            $notify->setHttpHeaders([
                'x-app-id' => $app->{App::APP_KEY}
            ]);
            $ret = $notify->send($app->{App::NOTIFY_URL}, $data, $app->{App::APP_SECRET});

            if ($ret) {
                return;
            }
        } catch (\Exception $exception) {
            // nothing to do
        }

        throw new NotifyFailedException();
    }
}

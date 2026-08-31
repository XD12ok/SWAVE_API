<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    public function __construct(protected EventService $events) {}

    public function index(Request $request): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            echo "retry: 5000\n\n";
            echo 'data: '.json_encode($this->events->snapshot())."\n\n";
            ob_flush();
            flush();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                echo 'data: '.json_encode($this->events->snapshot())."\n\n";
                ob_flush();
                flush();

                sleep(15);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }
}

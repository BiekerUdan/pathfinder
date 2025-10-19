<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 2025-01-19
 * Time: 12:00
 */

namespace Exodus4D\Pathfinder\Controller;

use Exodus4D\Pathfinder\Lib\Config;

class CronController extends Controller {

    /**
     * Trigger cron execution via HTTP request
     * Requires valid token for authentication
     * @param \Base $f3
     */
    public function trigger(\Base $f3){
        // Get configured token
        $configuredToken = Config::getEnvironmentData('CRON_TOKEN');

        // Get token from request
        $requestToken = $f3->get('GET.token') ?: $f3->get('POST.token');

        // Check if token is configured and valid
        if(empty($configuredToken)){
            $f3->status(404);
            echo json_encode([
                'error' => 'Not Found',
                'message' => 'Remote cron triggering is not enabled. Please set CRON_TOKEN in environment.ini or conf/environment.ini'
            ]);
            return;
        }

        if(empty($requestToken) || $requestToken !== $configuredToken){
            $f3->status(403);
            echo json_encode([
                'error' => 'Forbidden',
                'message' => 'Invalid or missing token'
            ]);
            return;
        }

        // Execute cron jobs
        try {
            $cron = \Exodus4D\Pathfinder\Lib\Cron::instance();

            // Get execution info before running
            $jobsBefore = $cron->getJobsConfig();

            // Run cron (synchronously to ensure completion before response)
            // run($time = null, $async = false)
            $cron->run(null, false);

            // Get updated job info after running
            $jobsAfter = $cron->getJobsConfig();

            // Build response with job execution info
            $executedJobs = [];
            foreach($jobsAfter as $name => $jobData){
                if(isset($jobData->lastExecStart)){
                    $executedJobs[] = [
                        'name' => $name,
                        'lastExecStart' => $jobData->lastExecStart ?? null,
                        'lastExecEnd' => $jobData->lastExecEnd ?? null,
                        'lastExecState' => $jobData->lastExecState ?? null,
                    ];
                }
            }

            $f3->status(200);
            echo json_encode([
                'status' => 'ok',
                'message' => 'Cron execution triggered',
                'timestamp' => date('Y-m-d H:i:s'),
                'jobs' => $executedJobs
            ], JSON_PRETTY_PRINT);

        } catch(\Exception $e){
            $f3->status(500);
            echo json_encode([
                'error' => 'Cron execution failed',
                'message' => $e->getMessage()
            ]);
        }
    }
}

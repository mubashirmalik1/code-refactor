<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        if ($user_id = $request->get('user_id')) {
            return $this->repository->getUsersJobs($user_id);
        }

        if (in_array($request->__authenticatedUser->user_type, [env('ADMIN_ROLE_ID'), env('SUPERADMIN_ROLE_ID')])) {
            return $this->repository->getAll($request);
        }

        return [];
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        return $this->repository->with('translatorJobRel.user')->find($id);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        return $this->repository->store($request->__authenticatedUser, $request->all());
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->all();
        $cuser = $request->__authenticatedUser;
        return $this->repository->updateJob($id, array_except($data, ['_token', 'submit']), $cuser);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        return $this->repository->storeJobEmail($request->all());
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if ($user_id = $request->get('user_id')) {
            return $this->repository->getUsersJobsHistory($user_id, $request);
        }

        return null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        return $this->repository->acceptJob($request->all(), $request->__authenticatedUser);
    }

    public function acceptJobWithId(Request $request)
    {
        return $this->repository->acceptJobWithId($request->get('job_id'), $request->__authenticatedUser);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        return $this->repository->cancelJobAjax($request->all(), $request->__authenticatedUser);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        return $this->repository->endJob($request->all());
    }

    public function customerNotCall(Request $request)
    {
        return $this->repository->customerNotCall($request->all());
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        return $this->repository->getPotentialJobs($request->__authenticatedUser);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $distance = $request->get('distance', '');
        $time = $request->get('time', '');
        $jobid = $request->get('jobid', '');
        $session = $request->get('session', '');

        $flagged = 'no';
        if ($data['flagged'] == 'true') {
            if ($data['admincomment'] == '') return "Please, add comment";
            $flagged = 'yes';
        }

        $manually_handled = 'no';
        if ($data['manually_handled'] == 'true') {
            $manually_handled = 'yes';
        }

        $by_admin = 'no';
        if ($data['by_admin'] == 'true') {
            $by_admin = 'yes';
        }

        $admincomment = $request->get('admincomment', '');

        if ($time || $distance) {
            Distance::where('job_id', '=', $jobid)->update(array('distance' => $distance, 'time' => $time));
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {
            Job::where('id', '=', $jobid)->update([
                'admin_comments' => $admincomment,
                'flagged' => $flagged,
                'session_time' => $session,
                'manually_handled' => $manually_handled,
                'by_admin' => $by_admin
            ]);
        }

        return 'Record updated!';
    }

    public function reopen(Request $request)
    {
        return $this->repository->reopen($request->all());
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return ['success' => 'Push sent'];
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return string[]
     */
    public function resendSMSNotifications(Request $request)
    {
        $job = $this->repository->find($request->get('jobid'));

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return ['success' => 'SMS sent'];
        } catch (\Exception $e) {
            return ['success' => $e->getMessage()]; // should be error instead of success
        }
    }
}

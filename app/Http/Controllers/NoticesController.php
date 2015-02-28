<?php namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Requests\PrepareNoticeRequest;
use App\Notice;
use App\Provider;
use Auth;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;


/**
 * Class NoticesController
 * @package App\Http\Controllers
 */
class NoticesController extends Controller {

    /**
     * Create a new notices controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');

        parent::__construct();
    }

    /**
     * Return all current notices as default index.
     * @return mixed
     */
    public function index()
    {
        return $this->user->notices;
    }

    /**
     * Show a page to create a new notice.
     * @return \Response
     */
    public function create()
    {
        $providers = Provider::lists('name', 'id');

        //load view to create notice
        return view('notices.create', compact('providers'));
    }

    /**
     * Ask user to confirm the DMCA notice to be delivered.
     * @param PrepareNoticeRequest $request
     * @return array
     */
    public function confirm(PrepareNoticeRequest $request)
    {
        $template = $this->compileDmcaTemplate($data = $request->all());
        session()->flash('dmca', $data);
        return view('notices.confirm', compact('template'));
    }

    /**
     * Store a new DMCA notice.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
        $notice = $this->createNotice($request);

        Mail::queue('emails.dmca', compact('notice'), function($message) use($notice){
            $message->from($notice->getOwnerEmail())
                    ->to($notice->getRecipientEmail())
                    ->subject('DMCA Notice');
        });

        return redirect('notices');
    }

    /**
     * Compile the DMCA template from form data.
     * @param $data
     * @return \Illuminate\View\View
     */
    private function compileDmcaTemplate($data)
    {
        $data = $data + [
            'name' => $this->user->name,
            'email' => $this->user->email,
        ];
        $template = view()->file(app_path('Http/Templates/dmca.blade.php'), $data);

        return view('notices.confirm', compact('template'));
    }

    /**
     * Create and persist a new DMCA notice.
     * @param Request $request
     * @return mixed
     */
    private function createNotice(Request $request)
    {
        $notice = session()->get('dmca') + ['template' => $request->input('template')];

        $notice = $this->user->notices->create($notice);

        return $notice;
    }

}

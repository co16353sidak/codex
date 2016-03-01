<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Contests_Index extends Controller_Base_preDispatch
{

    public function action_showAllContests()
    {
        $this->title = "Конкурсы команды CodeX";
        $this->description = "Здесь собраны конкурсы, которые проводятся внутри нашей команды";

        $contests = Model_Contests::getActiveContests();

        foreach ($contests as $contest) {

            if ( $contest->dt_close > date("Y-m-d H:m:s") ) 
                $this->view["contests"]['opened'][] = $contest;
            else 
                $this->view["contests"]['closed'][] = $contest;

        }

        $this->template->content = View::factory('templates/contests/list', $this->view);
    }

    public function action_showContest()
    {
        $contestId = $this->request->param('contest_id');

        $contest = Model_Contests::get($contestId);
        if ($contest->id == 0){
            throw new HTTP_Exception_404();
        }


        /** Add remaining days value */
        if ($contest->dt_close){

            $remainingTime = strtotime($contest->dt_close) - time();
            $contest->daysRemaining = floor( $remainingTime / Date::DAY );
        }

        /**
        * Add winner User information
        */
        if ($contest->winner) {
            $contest->winner = Model_User::get($contest->winner);
        }

        $this->view["contest"] = $contest;

        $this->title = $contest->title;
        $this->description = "Небольшой конкурс внутри НИУ ИТМО, который позволит вам показать свой творческий и профессиональный потенциал, соревнуясь за небольшие презенты.";

        $this->template->content = View::factory('templates/contests/contest', $this->view);
    }

}
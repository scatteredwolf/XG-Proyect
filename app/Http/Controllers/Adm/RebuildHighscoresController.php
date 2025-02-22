<?php

declare(strict_types=1);

namespace App\Http\Controllers\Adm;

use App\Core\BaseController;
use App\Core\Objects;
use App\Libraries\Adm\AdministrationLib as Administration;
use App\Libraries\FormatLib as Format;
use App\Libraries\Functions;
use App\Libraries\StatisticsLibrary as Statistics;
use App\Models\Libraries\StatisticsLibrary as StatisticsModel;
use App\Models\Game\Fleet;
use App\Models\Adm\Fleets;
use App\Libraries\FleetsLib;

class RebuildHighscoresController extends BaseController
{
    private array $result = [];

    public function __construct()
    {
        parent::__construct();

        // check if session is active
        Administration::checkSession();

        // load Language
        parent::loadLang(['adm/global', 'adm/rebuildhighscores']);
    }

    public function index(): void
    {
        // check if the user is allowed to access
        if (!Administration::authorization(__CLASS__, (int) $this->user['user_authlevel'])) {
            die(Administration::noAccessMessage($this->langs->line('no_permissions')));
        }

        // time to do something
        $this->runAction();

        // build the page
        $this->buildPage();
    }

    /**
     * Run an action
     *
     * @return void
     */
    private function runAction(): void
    {
        $modelsObject = new StatisticsModel();

        /*
        * Reset all Users Shippoints to 0
        * then calculates the points of all ships in each fleet
        * and adds these to the corresponding user
        */
        $fleetsModel = new Fleets();
        $fleetsModel->getAllFleets();
        $modelsObject->deletePoints("ships");
        foreach ($fleetsModel->getAllFleets() as $fleet) {
            $totalPoints = 0;
            foreach (FleetsLib::getFleetShipsArray($fleet['fleet_array']) as $ship => $amount) {
                $totalPoints += Statistics::calculatePoints($ship,1) * $amount;
            }
            $modelsObject->modifyPoints("ships", $totalPoints, $fleet['fleet_owner']);
        }
        
        // Sums up all ships on planets and adds these to the corresponding user
        $fleet = new Fleet();
        $stationaryFleets = $fleet->getAllShipsWithUser();
        foreach ($stationaryFleets as $fleet) {
            $totalPoints = 0;
            foreach ($fleet as $ship => $amount) {
                if ($ship != 'planet_user_id') {
                    $totalPoints += Statistics::calculatePoints(array_search($ship, Objects::getInstance()->getObjects()),1) * $amount;
                }
            }
            $modelsObject->modifyPoints("ships", $totalPoints, $fleet['planet_user_id']);
        }

        
        $stObject = new Statistics();
        $this->result = $stObject->makeStats();
        Functions::updateConfig('stat_last_update', $this->result['stats_time']);
    }

    private function buildPage(): void
    {
        $this->page->displayAdmin(
            $this->template->set(
                'adm/rebuildhighscores_view',
                array_merge(
                    $this->langs->language,
                    $this->getStatisticsResult()
                )
            )
        );
    }

    /**
     * Get the statistics regeneration results
     *
     * @return array
     */
    private function getStatisticsResult(): array
    {
        return [
            'memory_p' => strtr('%i / %m', [
                '%i' => Format::prettyBytes($this->result['memory_peak'][0]),
                '%m' => Format::prettyBytes($this->result['memory_peak'][0]),
            ]),
            'memory_i' => strtr('%i / %m', [
                '%i' => Format::prettyBytes($this->result['initial_memory'][0]),
                '%m' => Format::prettyBytes($this->result['initial_memory'][0]),
            ]),
            'memory_e' => strtr('%i / %m', [
                '%i' => Format::prettyBytes($this->result['end_memory'][0]),
                '%m' => Format::prettyBytes($this->result['end_memory'][0]),
            ]),
            'alert' => Administration::saveMessage('ok', strtr(
                $this->langs->line('sb_stats_update'),
                ['%t' => $this->result['totaltime']]
            )),
        ];
    }
}

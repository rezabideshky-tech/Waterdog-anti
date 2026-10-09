<?php
/*
* Copyright (C) Sergittos - All Rights Reserved
* Unauthorized copying of this file, via any medium is strictly prohibited
* Proprietary and confidential
*/

declare(strict_types=1);

namespace sergittos\bedwars\form\report;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\libs\EasyUI\element\Dropdown;
use sergittos\bedwars\libs\EasyUI\element\Input;
use sergittos\bedwars\libs\EasyUI\element\Label;
use sergittos\bedwars\libs\EasyUI\element\Option;
use sergittos\bedwars\libs\EasyUI\utils\FormResponse;
use sergittos\bedwars\libs\EasyUI\variant\CustomForm;
use sergittos\bedwars\report\ReportReasons;
use sergittos\bedwars\session\Session;
use function mb_substr;
use function trim;

/**
 * Second and final screen of the report flow: pick a reason and (optionally)
 * add a short description. Submission itself is fully delegated to
 * BedWarsCore's ReportService, which is the single place that validates,
 * persists and alerts staff - identical whichever server the report was
 * actually filed from.
 */
class ReportReasonForm extends CustomForm {

    private const MAX_DESCRIPTION_LENGTH = 200;

    public function __construct(
        private Session $session,
        private string $reportedUsername
    ) {
        parent::__construct(TF::RED . TF::BOLD . "Report " . TF::RESET . TF::WHITE . $this->reportedUsername);
    }

    protected function onCreation(): void {
        $this->addElement("info", new Label(
            TF::GRAY . "You're reporting " . TF::WHITE . $this->reportedUsername . TF::GRAY . "." . "\n" .
            TF::GRAY . "Choose the reason that best fits what happened."
        ));

        $dropdown = new Dropdown("Reason");
        foreach(ReportReasons::getAll() as $reason) {
            $dropdown->addOption(new Option($reason->getId(), $reason->getDisplayLabel()));
        }
        $this->addElement("reason", $dropdown);

        $this->addElement("description", new Input(
            "Additional details (optional)",
            "",
            "Describe what happened, e.g. \"broke our bed while cheating with X-Ray\""
        ));
    }

    protected function onSubmit(Player $player, FormResponse $response): void {
        $reasonId = $response->getDropdownSubmittedOptionId("reason");
        $description = mb_substr(trim($response->getInputSubmittedText("description")), 0, self::MAX_DESCRIPTION_LENGTH);

        $error = BedWarsCore::getInstance()->getReportService()->submitReport(
            $player,
            $this->reportedUsername,
            $reasonId,
            $description
        );

        if($error !== null) {
            $this->session->message($error);
            return;
        }

        $this->session->message("{GREEN}Thank you, your report against {GOLD}" . $this->reportedUsername . "{GREEN} has been submitted to our staff team!");
    }

}

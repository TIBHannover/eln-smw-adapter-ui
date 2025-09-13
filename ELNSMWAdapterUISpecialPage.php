<?php
/**
 * @deprecated This file is deprecated. Please use includes/SpecialELNSMWAdapterUI.php
 * This file is kept for backward compatibility and will be removed in a future version.
 */

// For backward compatibility, redirect to the new namespaced class
if (class_exists('ELNSMWAdapterUI\\SpecialELNSMWAdapterUI')) {
    class_alias('ELNSMWAdapterUI\\SpecialELNSMWAdapterUI', 'ELNSMWAdapterUISpecialPage');
    return;
}

class ELNSMWAdapterUISpecialPage extends SpecialPage {
    function __construct() {
        parent::__construct( 'ELNSMWAdapterUI' );
        $this->message_content = '';
    }

    function execute( $par ) {
        global $egELNSMWAdapterUIServiceURL;
        $out = $this->getOutput();
        $out->addJsConfigVars('egELNSMWAdapterUIServiceURL', $egELNSMWAdapterUIServiceURL);
        $this->setHeaders();

        $response_content = '';
        $display_form = '';
        $display_response = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $result = $this->adapt_protocols($_POST["eln-url"]);
            if($result) {
                if (array_key_exists('smw_pages', $result)) {
                    $created_pages = $result->smw_pages;
                    foreach ($created_pages as $key => $value) {
                        if (strpos($key, 'P') === 0) {
                            $response_content .= "<a href='https://service.tib.eu/sfb1368/wiki/$key'>$key</a>" . "<br>";
                        }
                    }
                } else {
                    $this->message_content .= "<span class='log warning'>[warning] No protocols created</span>" . "<br>";
                }

                if (array_key_exists('messages', $result)) {
                    $messages = $result->messages;
                    foreach ($messages as $message) {
                        $this->message_content .= "<span class='log " . $message->type . "'>[" . $message->type . "] " . $message->text . "</span>" . "<br>";
                    }
                }
            }

            $display_form = 'd-none';
        } else {
            $display_response = 'd-none';
        }

        //$response_content = '1';



        $html = <<<HTML
        <style>
            .log.error {
                color: red;
                font-weight: bold;
            }
            .log.warning {
                color: orange;
                font-weight: bold;
            }
        </style>
        <div class="eln-container">
            <form class="$display_form" id="eln-form" method="post">
                <p class="help-block">This Adapter imports Protocols from eLabFTW Experiments with a specified structure of the TU Clausthal.<br/> The prerequisite for this are corresponding protocol templates to these experiments in this Wiki.<br/> For further details and usage, please visit <a href="/sfb1368/wiki/Manual:eLabFTW_Adapter">Manual:eLabFTW Adapter</a>.</p>
                <div class="form-row">
                    <div class="col">
                        <div class="form-group mb-1">
                            <label for="eln-url" class="font-weight-bold mb-1 ">URL of the experiment page</label>
                            <input type="text" class="form-control" id="eln-url" name="eln-url" required placeholder="https://elab.tu-clausthal.de/experiments.php?mode=view&id=0000">
                        </div>
                        <input type="submit" class="btn border-0" value="Import Protocols">
                    </div>
                </div>
            </form>
            <div class="$display_response" id="response-area">
                <h2>Imported Protocols</h2>
                $response_content
                <h2>Log</h2>
                $this->message_content
            </div>
        </div>
        HTML;

        $out->addHTML( $html );
        $hide = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'eln-form' : 'response-area';
    }

    function adapt_protocols($eln_url) {
        $experiment_url = parse_url($eln_url);
        if (array_key_exists('host', $experiment_url)) {

            // determine ELN by url host
            switch ($experiment_url['host']) {
                case "elab.tu-clausthal.de":
                    parse_str($experiment_url['query'], $query);
                    if (array_key_exists('id', $query)) {
                        $r = $this->call_adapter('eLabFTW', $query['id']);
                        if($r) {
                            return json_decode($r);
                        } else {
                            $this->message_content .= "<span class='log error'>[error] Service offline. Please contact your administrator.</span>" . "<br>";
                            return null;
                        }
                    } else {
                        $this->message_content .= "<span class='log error'>[error] Missing url parameter: id</span>" . "<br>";
                        return null;
                    }
                    break;
                default:
                    $this->message_content .= "<span class='log error'>[error] No ELN specified for this url</span>" . "<br>";
                    return null;
            }
        } else {
            $this->message_content .= "<span class='log error'>[error] No valid url</span>" . "<br>";
            return null;
        }
    }

    function call_adapter($eln, $id) {
        global $egELNSMWAdapterUIServiceURL;
        $url = $egELNSMWAdapterUIServiceURL . "/adapt";
        $data = array('eln' => 'eLabFTW', 'id' => $id);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $r = curl_exec($curl);
        curl_close($curl);
        return $r;
    }
}

<pre>
<?php
    // ini_set('display_errors', 1);
    // ini_set('display_startup_errors', 1);
    // error_reporting(E_ALL);

    require("config.php");

    function getTime($timestamp, $format, $timezone) {
        $timeInTimezone = new DateTime("now", new DateTimeZone($timezone));
        $timeInTimezone->setTimestamp($timestamp);
        
        return $timeInTimezone->format($format);
    }

    function getCameraApiData() {
        global $config;
        global $selectedCamera;
        global $selectedDate;

        $fetchCameraApiData = curl_init();

        curl_setopt($fetchCameraApiData, CURLOPT_URL, $config["cameraApi"]["baseUrl"]."/cgi-bin/gw.cgi?xml=%3Cjuan%20ver=%220%22%20squ=%22_00000_%22%20dir=%220%22%20enc=%221%22%3E%3Crecsearch%20usr=%22".$config["cameraApi"]["credentials"]["username"]."%22%20pwd=%22".$config["cameraApi"]["credentials"]["password"]."%22%20channels=%22".$config["cameraApi"]["channels"][$selectedCamera]["view"]."%22%20types=%222%22%20date=%22".$selectedDate."%22%20begin=%220:0:0%22%20end=%2223:59:59%22%20session_index=%22".($_GET["page"]*$config["cameraApi"]["sessionsPerRequest"])."%22%20session_count=%22".$config["cameraApi"]["sessionsPerRequest"]."%22/%3E%3C/juan%3E&_=_00000_");
        curl_setopt($fetchCameraApiData, CURLOPT_RETURNTRANSFER, 1);
                
        $cameraApiResponse = curl_exec($fetchCameraApiData);
        
        curl_close($fetchCameraApiData);

        return $cameraApiResponse;
    }

    function renderTimeNavigation() {
        global $selectedDate;
        
        echo "<a href='?camera=".$_GET["camera"]."&date=".$selectedDate."&page=".($_GET["page"]+1)."'>Earlier</a>";

        if ($_GET["page"] != 0) {
            echo "&nbsp;|&nbsp;";
            echo "<a href='?camera=".$_GET["camera"]."&date=".$selectedDate."&page=".($_GET["page"]-1)."'>Later</a>";
        }

        echo "<br>";
        echo "<br>";
    }

    function renderDayNavigation() {
        global $config;
        global $selectedDate;
        global $today;

        echo "<a href='?camera=".$_GET["camera"]."&date=".getTime(strtotime("-1 day ", strtotime($selectedDate)), "Y-m-d", $config["cameraApi"]["timezoneCameraOffset"])."&page=0'>Previous Day</a>"; 

        if ($selectedDate !== $today) {
            echo "&nbsp;|&nbsp;";
            echo "<a href='?camera=".$_GET["camera"]."&date=".getTime(strtotime("+1 day ", strtotime($selectedDate)), "Y-m-d", $config["cameraApi"]["timezoneCameraOffset"])."&page=0'>Next Day</a>";
        }

        echo "<br>";
        echo "<br>";
    }

    function renderAllMotionSessions($cameraApiData) {
        global $config;
        global $selectedCamera;
        
        foreach ($cameraApiData->recsearch->s as $sessionKey => $sessionString) {

            $motionSessionDataRaw = explode("|", $sessionString);

            $motionSessionData = [
                "id" => $motionSessionDataRaw[1],
                "start" => [
                    "timestamp" => $motionSessionDataRaw[4],
                    "formatted" => getTime(strtotime("+".$config["cameraApi"]["secondsOffset"]." seconds", $motionSessionDataRaw[4]), "F j, Y g:i:s A", $config["cameraApi"]["timezoneCameraOffset"])
                ],
                "end" => [
                    "timestamp" => $motionSessionDataRaw[5],
                    "formatted" => getTime(strtotime("+".$config["cameraApi"]["secondsOffset"]." seconds", $motionSessionDataRaw[5]), "g:i:s A", $config["cameraApi"]["timezoneCameraOffset"])
                ],
                "duration" => $motionSessionDataRaw[5] - $motionSessionDataRaw[4],
                "periodicSnapshots" => [],
                "download" => [
                    "link" => $config["cameraApi"]["baseUrl"]."/cgi-bin/flv.cgi?u=".$config["cameraApi"]["credentials"]["username"]."&p=".$config["cameraApi"]["credentials"]["password"]."&mode=time&chn=".$config["cameraApi"]["channels"][$selectedCamera]["download"]."&begin=".$motionSessionDataRaw[4]."&end=".$motionSessionDataRaw[5]."&mute=false&download=1&rnd=".rand(0, 999999)
                ]
            ];

            for ($timestampTick = strtotime("+".($config["cameraApi"]["secondsOffset"] - $config["thresholdToMatchPeriodicSnapshots"])." seconds", $motionSessionData["start"]["timestamp"]); $timestampTick <= strtotime("+".($config["cameraApi"]["secondsOffset"] + $config["thresholdToMatchPeriodicSnapshots"])." seconds", $motionSessionData["end"]["timestamp"]); $timestampTick++) {
                if (date("i", $timestampTick)[1] % $config["periodicSnapshots"]["interval"] == 0 && date("s", $timestampTick) == 00) {
                    if ($timestampTick < time()) {
                        array_push($motionSessionData["periodicSnapshots"], $timestampTick);
                    }
                }
            }

            if ($motionSessionData["duration"] >= $config["secondsThresholdToShowMotionSession"]) {
                renderMotionSession($motionSessionData);
            }
        }
    }

    function renderMotionSession($motionSessionData) {
        global $config;
        global $selectedCamera;

        $sessionDirectory = "videos/".$config["cameraApi"]["channels"][$selectedCamera]["label"]."/".$motionSessionData["id"];

        echo "<div id='".$motionSessionData["id"]."'>";
        echo "<h2>".$motionSessionData["start"]["formatted"]." to ".$motionSessionData["end"]["formatted"]." ET</h2>";
        echo "<h3>#".$motionSessionData["id"]." &nbsp;";
        echo $motionSessionData["duration"]." seconds</h3>";

        foreach ($motionSessionData["periodicSnapshots"] as $periodicSnapshotKey => $periodicSnapshotTimestamp) {
            if (getTime(time(), "Y-m-d-H-i", $config["cameraApi"]["timezoneActual"]) > getTime($periodicSnapshotTimestamp, "Y-m-d-H-i", $config["cameraApi"]["timezoneCameraOffset"])) {
                echo getTime($periodicSnapshotTimestamp, "g:i A", $config["cameraApi"]["timezoneCameraOffset"]);
                echo "<br>";    
                echo "<img src='".$config["periodicSnapshots"]["url"].$config["cameraApi"]["channels"][$selectedCamera]["snapshots"]."/".getTime($periodicSnapshotTimestamp, "Y-m-d-H-i", $config["cameraApi"]["timezoneCameraOffset"]).".jpeg'>";
                echo "<br>";
                echo "<br>";
            }
        }

        if (file_exists($sessionDirectory."/".$motionSessionData["id"].'.mp4')) {
            echo "Video";
            echo "<br>";
            
            echo "<video width='640' controls><source src='".$sessionDirectory."/".$motionSessionData["id"].'.mp4'."' type='video/mp4'></video>";
            echo "<br>";
        } else {
            echo "<a href='processVideo.php?type=mp4&camera=".$config["cameraApi"]["channels"][$selectedCamera]["label"]."&motionSessionId=".$motionSessionData["id"]."&url=".urlencode($motionSessionData["download"]["link"])."'>Generate MP4</a>";
            echo "<br>";
        }

        if (file_exists($sessionDirectory."/".$config["frameDirectories"]["key"]."/")) {
            $keyFrames = scandir($sessionDirectory."/".$config["frameDirectories"]["key"]."/");
            
            echo "<br>";
            echo "Key Frames";
            echo "<br>";

            foreach ($keyFrames as $keyFrameKey => $keyFrameFilename) {
                if (strpos($keyFrameFilename, "jpeg") && $keyFrameFilename !== "." && $keyFrameFilename !== "..") {
                    echo "<img src='".$sessionDirectory."/".$config["frameDirectories"]["key"]."/".$keyFrameFilename."' width='320'>";
                }
            }

            echo "<br>";
        } else {
            echo "<br>";
            echo "<a href='processVideo.php?type=keyframes&camera=".$config["cameraApi"]["channels"][$selectedCamera]["label"]."&motionSessionId=".$motionSessionData["id"]."&url=".urlencode($motionSessionData["download"]["link"])."'>Generate Key Krames</a>";
            echo "<br>";
        }

        echo "<br>";

        if (file_exists($sessionDirectory."/".$config["frameDirectories"]["all"]."/")) {
            $allFrames = scandir($sessionDirectory."/".$config["frameDirectories"]["all"]."/");
            
            echo "All Frames";
            echo "<br>";

            foreach ($allFrames as $allFrameKey => $allFrameFilename) {
                if (strpos($allFrameFilename, "jpeg") && $allFrameFilename !== "." && $allFrameFilename !== "..") {
                    echo "<img src='".$sessionDirectory."/".$config["frameDirectories"]["all"]."/".$allFrameFilename."' width='320'>";
                }
            }
        } else {
            echo "<a href='processVideo.php?type=allframes&camera=".$config["cameraApi"]["channels"][$selectedCamera]["label"]."&motionSessionId=".$motionSessionData["id"]."&url=".urlencode($motionSessionData["download"]["link"])."'>Generate All Frames</a>";
        }

        echo "<br>";
        echo "<br>";
        echo "<br>";
        echo "</div>";
    }

    function renderPage($cameraApiResponse) {
        global $config;
        global $selectedDate;
        global $today;

        echo "<h1>";
            if ($selectedDate === $today) { echo "Today "; }
            echo getTime(strtotime($selectedDate), "F j, Y", $config["cameraApi"]["timezoneCameraOffset"]);
        echo "</h1>";

        renderDayNavigation();
        renderTimeNavigation();

        $cameraApiData = simplexml_load_string($cameraApiResponse);

        if ($cameraApiData === false) {
            echo "Failed parsing camera API response XML...";

            foreach (libxml_get_errors() as $error) {
                echo "<br>", $error->message;
            }
        } else {
            renderAllMotionSessions($cameraApiData);   
            renderTimeNavigation();
            renderDayNavigation();
        }

        echo "<br>";
        echo "<br>";
        echo "<br>";
    }

    if (isset($_GET["camera"])) {
        $selectedCamera = $_GET["camera"];
        $today = getTime(time(), "Y-m-d", $config["cameraApi"]["timezoneActual"]);

        if (isset($_GET["date"])) {
            $selectedDate = $_GET["date"];
        } else {
            $selectedDate = $today;
        }
        
        $cameraApiResponse = getCameraApiData();

        renderPage($cameraApiResponse);
    } else {
        foreach ($config["cameraApi"]["channels"] as $camera) {
            echo "<a href='?camera=".$camera["label"]."'>".$camera["name"]."</a>";
            echo "<br>";
        }
    }

?>
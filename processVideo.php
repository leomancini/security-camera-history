<?php
    require("config.php");

    $motionSessionStreamDownloadUrl = urldecode($_GET["url"]);
    $motionSessionId = $_GET["motionSessionId"];
    $camera = $_GET["camera"];
    $cameraDirectory = $config["videosDirectory"]."/".$camera;

    if (!file_exists($config["videosDirectory"])) {
        mkdir($config["videosDirectory"]);
    }

    if (!file_exists($cameraDirectory)) {
        mkdir($cameraDirectory);
    }

    if (!file_exists($cameraDirectory."/".$motionSessionId."/")) {
        mkdir($cameraDirectory."/".$motionSessionId."/");
    }

    $motionSessionFilename = $cameraDirectory."/".$motionSessionId."/".$motionSessionId;
    $motionSessionStreamFilename = $motionSessionFilename.".".$config["streamExtension"];
    $motionSessionEncodedVideoFilename = $motionSessionFilename.".".$config["encodedVideoExtension"];

    $motionSessionVideoStreamDownloaded = false;

    if (file_exists($motionSessionStreamFilename)) {
        $motionSessionVideoStreamDownloaded = true;
    } else {
        $motionSessionStreamFile = fopen($motionSessionStreamFilename, "w+");

        if($motionSessionStreamFile === false) {
            throw new Exception("Error: Couldn't create file for ".$motionSessionStreamFilename);
        }

        $fetchMotionSession = curl_init($motionSessionStreamDownloadUrl);

        curl_setopt($fetchMotionSession, CURLOPT_FILE, $motionSessionStreamFile);
        curl_setopt($fetchMotionSession, CURLOPT_TIMEOUT, 20);

        curl_exec($fetchMotionSession);
        
        if(curl_errno($fetchMotionSession)){
            throw new Exception(curl_error($fetchMotionSession));
        }

        $cameraApiResponseCode = curl_getinfo($fetchMotionSession, CURLINFO_HTTP_CODE);
        
        if ($cameraApiResponseCode === 200) {
            $motionSessionVideoStreamDownloaded = true;
        } else {
            $cameraApiErrorCode = $cameraApiResponseCode;
        }
        
        curl_close($fetchMotionSession);
        
        fclose($motionSessionStreamFile);
    }
    
    if($motionSessionVideoStreamDownloaded) {
        if ($_GET["type"] == $config["encodedVideoExtension"]) {
            exec($config["scriptPaths"]["handbrake"]." -i ".$motionSessionStreamFilename." -o ".$motionSessionEncodedVideoFilename." 2>&1");
        } else if (
            $_GET["type"] == $config["frameDirectories"]["key"] ||
            $_GET["type"] == $config["frameDirectories"]["all"]
        ) {
            if (!file_exists($motionSessionEncodedVideoFilename)) {
                exec($config["scriptPaths"]["handbrake"]." -i ".$motionSessionStreamFilename." -o ".$motionSessionEncodedVideoFilename." 2>&1");
            }

            if (!file_exists($cameraDirectory."/".$motionSessionId."/".$_GET["type"]."/")) {
                mkdir($cameraDirectory."/".$motionSessionId."/".$_GET["type"]."/");
            }

            if ($_GET["type"] == $config["frameDirectories"]["key"]) {
                exec($config["scriptPaths"]["ffmpeg"]." -skip_frame nokey -i ".$motionSessionEncodedVideoFilename." -vsync 0 -r 30 -f image2 videos/".$camera."/".$motionSessionId."/".$config["frameDirectories"]["key"]."/%04d.jpeg 2>&1");
            } else if ($_GET["type"] == $config["frameDirectories"]["all"]) {
                exec($config["scriptPaths"]["ffmpeg"]." -i ".$motionSessionEncodedVideoFilename." -vf fps=1 videos/".$camera."/".$motionSessionId."/".$config["frameDirectories"]["all"]."/%04d.jpeg 2>&1");
            }
        }

        header("Location: ".$_SERVER["HTTP_REFERER"]."#".$motionSessionId);
    } else {
        echo "Error: ".$cameraApiErrorCode;
    }
?>
<?php
    require("secrets.php");

    $config = [
        "videosDirectory" => "./videos",
        "frameDirectories" => [
            "key" => "keyframes",
            "all" => "allframes"
        ],
        "streamExtension" => "ts",
        "encodedVideoExtension" => "mp4",
        "scriptPaths" => [
            "ffmpeg" => "/usr/local/bin/ffmpeg",
            "handbrake" => "./bin/handbrake"
        ],
        "secondsThresholdToShowMotionSession" => 30, // Number of seconds that a motion session needs to be greater than in order to be shown on the page
        "thresholdToMatchPeriodicSnapshots" => 60 * 10, // Number of seconds before and after motion session to look for matching periodic snapshots
        "periodicSnapshots" => [
            "url" => $secrets["periodicSnapshots"]["url"], // Directory where periodic snapshots are stored
            "interval" => 5, // Number of minutes between periodic snapshot captures (must be synced with how often crontab runs)
            "credentials" => $secrets["periodicSnapshots"]["credentials"],
        ],
        "cameraApi" => [
            "baseUrl" => $secrets["cameraApi"]["baseUrl"],
            "credentials" => $secrets["cameraApi"]["credentials"],
            "channels" => $secrets["cameraApi"]["channels"],
            "timezoneActual" => "America/New_York",
            "timezoneCameraOffset" => "Atlantic/Azores",
            "secondsOffset" => 6182, // Number of seconds that camera timestamps are behind real time
            "sessionsPerRequest" => 10, // Number of sessions to fetch per request
        ]
    ];
?>
<?php

namespace whm\MissingRequest\PhantomJS;

use whm\Html\Uri;

class HarRetriever
{
    private $phantomJSExec = 'phantomjs';
    private $netsniffFile = 'netsniff.js';
    private $netSniffTempFile;
    private $commandTimeoutInSeconds;

    public function __construct($phantomJSExec = null, $commandTimeoutInSeconds = 20)
    {
        if (!is_null($phantomJSExec)) {
            $this->phantomJSExec = $phantomJSExec;
        }

        $this->commandTimeoutInSeconds = $commandTimeoutInSeconds;

        $this->netSniffTempFile = \tempnam('missing', 'netsniff_');
        copy(__DIR__ . '/' . $this->netsniffFile, $this->netSniffTempFile);
    }

    public function getHarFile(Uri $uri, $timeout = 1000)
    {
        $command = $this->phantomJSExec . ' ' . $this->netSniffTempFile . ' ' . (string)$uri . " " . $timeout . " '" . $uri->getCookieString() . "'";

        exec($command, $output, $exitCode);

        $rawOutput = implode($output, "\n");

        if ($exitCode > 0) {
            $e = new PhantomJsRuntimeException('Phantom exits with exit code ' . $exitCode . PHP_EOL . $rawOutput);
            $e->setExitCode($exitCode);
            throw $e;
        }

        $harStart = strpos($rawOutput, '##HARFILE-BEGIN') + 16;
        $harEnd = strpos($rawOutput, '##HARFILE-END');
        $harLength = $harEnd - $harStart;
        $harContent = substr($rawOutput, $harStart, $harLength);

        $htmlStart = strpos($rawOutput, '##CONTENT-BEGIN') + 15;

        $htmlEnd = strpos($rawOutput, '##CONTENT-END');
        $htmlLength = $htmlEnd - $htmlStart;
        $htmlContent = substr($rawOutput, $htmlStart, $htmlLength);

        return array('harFile' => new HarArchive(json_decode($harContent)), 'html' => $htmlContent);
    }

    public function __destruct()
    {
        unlink($this->netSniffTempFile);
    }
}

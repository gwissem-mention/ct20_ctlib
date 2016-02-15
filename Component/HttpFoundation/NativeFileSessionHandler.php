<?php
namespace CTLib\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler;

/**
 * NativeFileSessionHandler.
 *
 * Class used to replace Symfony's NativeFileSessionHandler to prevent its
 * session calls from triggering errors.
 */
class NativeFileSessionHandler extends NativeSessionHandler
{
    /**
     * Constructor.
     *
     * @param string $savePath Path of directory to save session files.
     *                         Default null will leave setting as defined by PHP.
     *                         '/path', 'N;/path', or 'N;octal-mode;/path
     *
     * @see http://php.net/session.configuration.php#ini.session.save-path for further details.
     *
     * @throws \InvalidArgumentException On invalid $savePath
     */
    public function __construct($savePath = null)
    {
        if (null === $savePath) {
            $savePath = ini_get('session.save_path');
        }

        $baseDir = $savePath;

        if ($count = substr_count($savePath, ';')) {
            if ($count > 2) {
                throw new \InvalidArgumentException(sprintf('Invalid argument $savePath \'%s\'', $savePath));
            }

            // characters after last ';' are the path
            $baseDir = ltrim(strrchr($savePath, ';'), ';');
        }

        if ($baseDir && !is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

    }
}

<?php
namespace CTLib\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * BinaryFileResponse represents an HTTP response delivering a file.
 * 
 * @author Shuang Liu <sliu@celltrak.com>
 * 
 * Copied most codes from Symfony 2.3 Symfony\Component\HttpFoundation\BinaryFileResponse
 * Once upgrading to Symfony > 2.3, we can remove this class.
 * 
 */
class BinaryFileResponse extends Response
{
    const DISPOSITION_INLINE     = "inline";
    const DISPOSITION_ATTACHMENT = "attachment"; //this will force download dialog

    protected static $trustXSendfileTypeHeader = false;

    protected $file;
    protected $offset;
    protected $maxlen;
    protected $request;

    /**
     * Constructor.
     *
     * @param SplFileInfo|string $file               The file to stream
     * @param Request            $request            Request
     * @param array              $headers            An array of response headers
     * @param null|string        $contentDisposition The type of Content-Disposition to set automatically with the filename
     */
    public function __construct($request, $file, 
        $downloadName = '', $headers = array(), $contentDisposition = null)
    {
        parent::__construct(null, 200, $headers);
        $this->request = $request;

        if (!$contentDisposition) {
            $contentDisposition = static::DISPOSITION_ATTACHMENT;
        }
        
        $this->setFile($file);
        $this->setContentDisposition($contentDisposition, $downloadName);
        
        $this->setPublic();
    }

    /**
     * Sets the file to stream.
     *
     * @param SplFileInfo|string $file The file to stream
     * @param string             DownloadableResponse::DISPOSITION_INLINE or DownloadableResponse::DISPOSITION_ATTACHMENT
     * @param Boolean            $autoEtag
     * @param Boolean            $autoLastModified
     *
     * @return BinaryFileResponse
     *
     * @throws FileException
     */
    public function setFile($file, $contentDisposition = null, $autoEtag = false, $autoLastModified = true)
    {
        $file = new File((string) $file);

        if (!$file->isReadable()) {
            throw new FileException('File must be readable.');
        }

        $this->file = $file;

        if ($autoEtag) {
            $this->setAutoEtag();
        }

        if ($autoLastModified) {
            $this->setAutoLastModified();
        }

        if ($contentDisposition) {
            $this->setContentDisposition($contentDisposition);
        }

        return $this;
    }

    /**
     * Gets the file.
     *
     * @return File The file to stream
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Automatically sets the Last-Modified header according the file modification date.
     */
    public function setAutoLastModified()
    {
        $this->setLastModified(\DateTime::createFromFormat('U', $this->file->getMTime()));

        return $this;
    }

    /**
     * Automatically sets the ETag header according to the checksum of the file.
     */
    public function setAutoEtag()
    {
        $this->setEtag(sha1_file($this->file->getPathname()));

        return $this;
    }

    /**
     * Sets the Content-Disposition header with the given filename.
     *
     * @param string $disposition      DownloadableResponse::DISPOSITION_INLINE or DownloadableResponse::DISPOSITION_ATTACHMENT
     * @param string $filename         Optionally use this filename instead of the real name of the file
     * @param string $filenameFallback A fallback filename, containing only ASCII characters. Defaults to an automatically encoded filename
     *
     * @return BinaryFileResponse
     */
    public function setContentDisposition($disposition, $filename = '', $filenameFallback = '')
    {
        if ($filename === '') {
            $filename = $this->file->getFilename();
        }

        $dispositionHeader = $this->makeDisposition($disposition, $filename, $filenameFallback);
        $this->headers->set('Content-Disposition', $dispositionHeader);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare()
    {
        $request = $this->request;
        
        $this->headers->set('Content-Length', $this->file->getSize());
        $this->headers->set('Accept-Ranges', 'bytes');
        $this->headers->set('Content-Transfer-Encoding', 'binary');

        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', $this->file->getMimeType() ?: 'application/octet-stream');
        }

        if ('HTTP/1.0' != $request->server->get('SERVER_PROTOCOL')) {
            $this->setProtocolVersion('1.1');
        }

        $this->ensureIEOverSSLCompatibility($request);

        $this->offset = 0;
        $this->maxlen = -1;

        if (self::$trustXSendfileTypeHeader && $request->headers->has('X-Sendfile-Type')) {
            // Use X-Sendfile, do not send any content.
            $type = $request->headers->get('X-Sendfile-Type');
            $path = $this->file->getRealPath();
            if (strtolower($type) == 'x-accel-redirect') {
                // Do X-Accel-Mapping substitutions.
                foreach (explode(',', $request->headers->get('X-Accel-Mapping', '')) as $mapping) {
                    $mapping = explode('=', $mapping, 2);

                    if (2 == count($mapping)) {
                        $location = trim($mapping[0]);
                        $pathPrefix = trim($mapping[1]);

                        if (substr($path, 0, strlen($pathPrefix)) == $pathPrefix) {
                            $path = $location.substr($path, strlen($pathPrefix));
                            break;
                        }
                    }
                }
            }
            $this->headers->set($type, $path);
            $this->maxlen = 0;
        } elseif ($request->headers->has('Range')) {
            // Process the range headers.
            if (!$request->headers->has('If-Range') || $this->getEtag() == $request->headers->get('If-Range')) {
                $range = $request->headers->get('Range');
                $fileSize = $this->file->getSize();

                list($start, $end) = explode('-', substr($range, 6), 2) + array(0);

                $end = ('' === $end) ? $fileSize - 1 : (int) $end;

                if ('' === $start) {
                    $start = $fileSize - $end;
                    $end = $fileSize - 1;
                } else {
                    $start = (int) $start;
                }

                $start = max($start, 0);
                $end = min($end, $fileSize - 1);

                $this->maxlen = $end < $fileSize ? $end - $start + 1 : -1;
                $this->offset = $start;

                $this->setStatusCode(206);
                $this->headers->set('Content-Range', sprintf('bytes %s-%s/%s', $start, $end, $fileSize));
            }
        }

        return $this;
    }

    /**
     * Sends the file.
     */
    public function sendContent()
    {
        if (!$this->isSuccessful()) {
            parent::sendContent();

            return;
        }

        if (0 === $this->maxlen) {
            return;
        }

        $out = fopen('php://output', 'wb');
        $file = fopen($this->file->getPathname(), 'rb');

        stream_copy_to_stream($file, $out, $this->maxlen, $this->offset);

        fclose($out);
        fclose($file);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException when the content is not null
     */
    public function setContent($content)
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a BinaryFileResponse instance.');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return false
     */
    public function getContent()
    {
        return false;
    }

    /**
     * Trust X-Sendfile-Type header.
     */
    public static function trustXSendfileTypeHeader()
    {
        self::$trustXSendfileTypeHeader = true;
    }

    /**
     * Check if we need to remove Cache-Control for ssl encrypted downloads when using IE < 9
     *
     * @link http://support.microsoft.com/kb/323308
     */
    protected function ensureIEOverSSLCompatibility(Request $request)
    {
        if (false !== stripos($this->headers->get('Content-Disposition'), 'attachment') 
            && preg_match('/MSIE (.*?);/i', $request->server->get('HTTP_USER_AGENT'), $match) == 1 
            && true === $request->isSecure()
        ) {
            if (intval(preg_replace("/(MSIE )(.*?);/", "$2", $match[0])) < 9) {
                $this->headers->remove('Cache-Control');
            }
        }
    }

    /**
     * Generates a HTTP Content-Disposition field-value.
     *
     * @param string $disposition      One of "inline" or "attachment"
     * @param string $filename         A unicode string
     * @param string $filenameFallback A string containing only ASCII characters that
     *                                 is semantically equivalent to $filename. If the filename is already ASCII,
     *                                 it can be omitted, or just copied from $filename
     *
     * @return string A string suitable for use as a Content-Disposition field-value.
     *
     * @throws \InvalidArgumentException
     * @see RFC 6266
     */
    public function makeDisposition($disposition, $filename, $filenameFallback = '')
    {
        if (!in_array($disposition, array(self::DISPOSITION_ATTACHMENT, self::DISPOSITION_INLINE))) {
            throw new \InvalidArgumentException(sprintf('The disposition must be either "%s" or "%s".', self::DISPOSITION_ATTACHMENT, self::DISPOSITION_INLINE));
        }

        if ('' == $filenameFallback) {
            $filenameFallback = $filename;
        }

        // filenameFallback is not ASCII.
        if (!preg_match('/^[\x20-\x7e]*$/', $filenameFallback)) {
            throw new \InvalidArgumentException('The filename fallback must only contain ASCII characters.');
        }

        // percent characters aren't safe in fallback.
        if (false !== strpos($filenameFallback, '%')) {
            throw new \InvalidArgumentException('The filename fallback cannot contain the "%" character.');
        }

        // path separators aren't allowed in either.
        if (false !== strpos($filename, '/') || false !== strpos($filename, '\\') || false !== strpos($filenameFallback, '/') || false !== strpos($filenameFallback, '\\')) {
            throw new \InvalidArgumentException('The filename and the fallback cannot contain the "/" and "\\" characters.');
        }

        $output = sprintf('%s; filename="%s"', $disposition, str_replace('"', '\\"', $filenameFallback));

        if ($filename !== $filenameFallback) {
            $output .= sprintf("; filename*=utf-8''%s", rawurlencode($filename));
        }

        return $output;
    }
}
<?php

namespace CTLib\Component\AWS;

use Aws\Sdk;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\CommandPool;
use Aws\CommandInterface;
use Aws\ResultInterface;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Helper service to interact with AWS S3 service.
 *
 * Author David McLean <dmclean@celltrak.com>
 */
class CtAwsS3
{
    const INTERFACE_IMPORT_FOLDER = 'interface_import';
    const INTERFACE_EXPORT_FOLDER = 'interface_export';
    const PURGE_ARCHIVE_FOLDER    = 'purge_archive';
    const PDF_FOLDER              = 'PDF';

    /**
     * List of allowed folders to
     * read and write.
     *
     * @var array $validFolders
     */
    protected $validFolders = [
        self::INTERFACE_IMPORT_FOLDER,
        self::INTERFACE_EXPORT_FOLDER,
        self::PURGE_ARCHIVE_FOLDER,
        self::PDF_FOLDER
    ];


    /**
     * @var string $region
     */
    protected $region;

    /**
     * @var string $bucket
     */
    protected $bucket;

    /**
     * @var string $key
     */
    protected $key;

    /**
     * @var string $secret
     */
    protected $secret;

    /**
     * @var string $siteId
     */
    protected $siteId;

    /**
     * @var Logger $logger
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param string $region
     * @param string $bucket
     * @param string $key
     * @param string secret
     * @param string $siteId
     * @param Logger $logger
     */
    public function __construct(
        $region,
        $bucket,
        $key,
        $secret,
        $siteId,
        $logger
    ) {
        $this->region = $region;
        $this->bucket = $bucket;
        $this->key    = $key;
        $this->secret = $secret;
        $this->siteId = $siteId;
        $this->logger = $logger;
    }

    /**
     * Writes content to AWS S3.
     *
     * @param string $folderPath
     * @param string $key
     * @param string $content
     *
     * @return string
     */
    public function putContent($folderPath, $key, $content)
    {
        if (!in_array($folderPath, $this->validFolders)) {
            $this->logger->error("AwsS3: invalid folder requested");
            throw new \Exception("Aws S3 - Invalid folder requested");
        }

        $awsS3Key = "{$folderPath}/{$this->siteId}/{$key}";

        // Get the common AWS configuration.
        $config = $this->getAwsConfig();

        // Create an instance of the AWS SDK and S3Client.
        $awsSdk = new Sdk($config);
        $s3Client = $awsSdk->createS3();

        try {
            // Upload data to S3.
            $s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $awsS3Key,
                'Body'   => $content,
                'ServerSideEncryption' => 'AES256'
            ]);
        } catch (S3Exception $e) {
            $this->logger->error("AwsS3: write {$awsS3Key} failed: {$e->getMessage()}");
            return null;
        }

        return $awsS3Key;
    }

    /**
     * Reads content from AWS S3.
     *
     * @param string $folderPath
     * @param string $key
     *
     * @return string
     */
    public function readContent($folderPath, $key)
    {
        $content = null;
        $awsS3Key = "{$folderPath}/{$this->siteId}/{$key}";

        // Get the common AWS configuration.
        $config = $this->getAwsConfig();

        // Create an instance of the AWS SDK and S3Client.
        $awsSdk = new Sdk($config);
        $s3Client = $awsSdk->createS3();

        try {
            // Get the object from S3
            $result = $s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $awsS3Key
            ]);

            $content = $result['Body'];
        } catch (S3Exception $e) {
            $this->logger->error("AwsS3: read {$awsS3Key} failed: {$e->getMessage()}");
            return null;
        }

        return $content;
    }

    /**
     * Writes a batch of files to AWS S3.
     *
     * @param string $srcFolderPath
     * @param string $destFolderPath
     *
     * @return bool
     */
    public function putBatchContent($srcFolderPath, $destFolderPath)
    {
        $result = true;

        // Get the common AWS configuration.
        $config = $this->getAwsConfig();

        // Create an instance of the AWS SDK and S3Client.
        $awsSdk = new Sdk($config);
        $s3Client = $awsSdk->createS3();

        // Create an iterator for the directory containing
        // the source data files.
        $files = new \DirectoryIterator($srcFolderPath);

        // Create a generator that converts the SplFileInfo objects into
        // Aws\CommandInterface objects. This generator accepts the iterator that
        // yields files and the name of the bucket to upload the files to.
        $cmdGenerator
            = function(\Iterator $files, $toBucket) use ($s3Client, $destFolderPath) {
            foreach ($files as $file) {
                // Skip "." and ".." files.
                if ($file->isDot()) {
                    continue;
                }
                $filename = $file->getPath() . '/' . $file->getFilename();
                $key = $destFolderPath
                    . "/{$this->siteId}/{$file->getFilename()}";
                // Yield a command that will be executed by the pool.
                yield $s3Client->getCommand('PutObject', [
                    'Bucket'               => $toBucket,
                    'Key'                  => $key,
                    'SourceFile'           => $filename,
                    'ServerSideEncryption' => 'AES256'
                ]);
            }
        };

        // Create the generator using the files iterator.
        $commands = $cmdGenerator($files, $this->bucket);

        // Create a pool.
        $pool = new CommandPool($s3Client, $commands, [
            'rejected' => function (
                AwsException $reason,
                $iterKey,
                PromiseInterface $aggregatePromise
            ) {
                $result = false;
                $this->logger()->error("AwsS3::writeBatch - data transfer to S3 failed: $reason");
            }
        ]);

        // Initiate the pool transfers
        $promise = $pool->promise();
        $promise->wait();

        return $result;
    }

    /**
     * Get the AWS SDK configuration that is shared
     * between all client services.
     *
     * @return array
     */
    protected function getAwsConfig()
    {
        return [
            'region'  => $this->region,
            'bucket'  => $this->bucket,
            'version' => 'latest',
            'credentials' => [
                'key'     => $this->key,
                'secret'  => $this->secret
            ]
        ];
    }
}

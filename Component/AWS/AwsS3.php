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
class AwsS3
{
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
     * @param Logger $logger
     */
    public function __construct(
        $region,
        $bucket,
        $key,
        $secret,
        $logger
    ) {
        $this->region       = $region;
        $this->bucket       = $bucket;
        $this->key          = $key;
        $this->secret       = $secret;
        $this->logger       = $logger;
    }

    /**
     * Writes content to AWS S3.
     *
     * @param string $key
     * @param string $content
     *
     * @return bool
     */
    public function putContent($key, $content)
    {
        // Get an instance of an AWS s3Client.
        $s3Client = $this->getS3Client();

        // Upload data to S3.
        $result = $s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
            'Body'   => $content,
            'ServerSideEncryption' => 'AES256'
        ]);

        return $result != null;
    }

    /**
     * Reads content from AWS S3.
     *
     * @param string $key
     *
     * @return string
     */
    public function getContent($key)
    {
        $content = null;

        // Get an instance of an AWS s3Client.
        $s3Client = $this->getS3Client();

        // Get the object from S3
        $result = $s3Client->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $key
        ]);

        if ($result && isset($result['Body'])) {
            $content = $result['Body'];
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

        // Get an instance of an AWS s3Client.
        $s3Client = $this->getS3Client();

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
                $key = $destFolderPath . '/' . $file->getFilename();
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
     * Deletes content from AWS S3.
     *
     * @param string $key
     *
     * @return bool
     */
    public function deleteContent($key)
    {
        // Get an instance of an AWS s3Client.
        $s3Client = $this->getS3Client();

        // Delete the object from S3
        $result = $s3Client->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => $key
        ]);

        return $result != null;
    }

    protected function getS3Client()
    {
        // Get the common AWS configuration.
        $config = $this->getAwsConfig();
        // Create an instance of the AWS SDK and S3Client.
        $awsSdk = new Sdk($config);
        return $awsSdk->createS3();
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

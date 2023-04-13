<?php
namespace CarloNicora\Minimalism\Services\DigitalOcean;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use CarloNicora\Minimalism\Abstracts\AbstractService;
use Exception;
use RuntimeException;
use Throwable;

class DigitalOcean extends AbstractService
{
    /** @var string */
    protected string $bucket;

    /** @var int */
    protected int $uploadExpiration;

    /** @var string */
    protected string $digitalOceanUrl;

    /** @var S3Client|null  */
    private ?S3Client $client=null;

    /** @var array */
    private const EXTENSIONS = [
        'bmp'   => 'image/bmp',
        'gif'   => 'image/gif',
        'ico'   => 'image/x-icon',
        'jpeg'  => 'image/jpeg',
        'jpg'   => 'image/jpeg',
        'pdf'   => 'application/pdf',
        'png'   => 'image/png',
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
    ];

    /**
     * AWS constructor.
     * @param string $MINIMALISM_SERVICE_DIGITALOCEAN_ACCESS_KEY
     * @param string $MINIMALISM_SERVICE_DIGITALOCEAN_ACCESS_SECRET
     * @param string $MINIMALISM_SERVICE_DIGITALOCEAN_REGION
     * @param string $MINIMALISM_SERVICE_DIGITALOCEAN_BUCKET
     * @param string $MINIMALISM_SERVICE_DIGITALOCEAN_ENDPOINT
     */
    public function __construct(
        private readonly string $MINIMALISM_SERVICE_DIGITALOCEAN_ACCESS_KEY,
        private readonly string $MINIMALISM_SERVICE_DIGITALOCEAN_ACCESS_SECRET,
        private readonly string $MINIMALISM_SERVICE_DIGITALOCEAN_REGION,
        private readonly string $MINIMALISM_SERVICE_DIGITALOCEAN_BUCKET,
        private readonly string $MINIMALISM_SERVICE_DIGITALOCEAN_ENDPOINT,
    )
    {
        $this->digitalOceanUrl = $this->MINIMALISM_SERVICE_DIGITALOCEAN_ENDPOINT;
    }

    public function getDigitalOceanPublicUrl(): string
    {
        return $this->digitalOceanUrl;
    }

    /**
     * @return S3Client
     */
    private function client(
    ): S3Client
    {
        if ($this->client === null) {
            $this->client = new S3Client([
                'credentials' => [
                    'key' => $this->MINIMALISM_SERVICE_DIGITALOCEAN_ACCESS_KEY,
                    'secret' => $this->MINIMALISM_SERVICE_DIGITALOCEAN_ACCESS_SECRET,
                ],
                'region' => $this->MINIMALISM_SERVICE_DIGITALOCEAN_REGION,
                'version' => 'latest',
                'endpoint' => $this->digitalOceanUrl
            ]);
        }

        return $this->client;
    }

    /**
     * @param string $localFile
     * @param string $remoteFile
     * @param string $extension
     * @return string|null
     * @throws Exception
     */
    public function upload(
        string $localFile,
        string $remoteFile,
        string $extension,
    ): ?string
    {
        try {
            $result = $this->client()->putObject([
                'Bucket' => $this->MINIMALISM_SERVICE_DIGITALOCEAN_BUCKET,
                'Key' => $remoteFile,
                'SourceFile' => $localFile,
                'ACL' => 'private',
                'ContentType' => self::EXTENSIONS[$extension]
            ]);
            return substr($result->get('ObjectURL'), strlen($this->digitalOceanUrl));
        } catch (S3Exception|Throwable $e) {
            throw new RuntimeException($e->getMessage(), 500);
        }
    }

    /**
     * @param string $fileName
     * @return bool
     */
    public function delete(
        string $fileName,
    ): bool
    {
        if (str_starts_with($fileName, '/')){
            $fileName = substr($fileName, 1);
        }

        $result = $this->client()->deleteObject([
            'Bucket' => $this->MINIMALISM_SERVICE_DIGITALOCEAN_BUCKET,
            'Key' => $fileName,
        ]);

        $response = $result['@metadata']['statusCode'];

        return $response === 204;
    }

    /**
     * @param string $fileName
     * @return bool
     */
    public function doesFileExists(
        string $fileName,
    ): bool
    {
        if (str_starts_with($fileName, '/')){
            $fileName = substr($fileName, 1);
        }

        return $this->client()->doesObjectExist(
            bucket: $this->MINIMALISM_SERVICE_DIGITALOCEAN_BUCKET,
            key: $fileName,
        );
    }
}
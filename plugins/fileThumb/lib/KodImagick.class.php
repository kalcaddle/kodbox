<?php 

/**
 * 图片处理类-Imagick扩展
 */
class KodImagick {

    // 支持的图像格式
    private const IMAGE_FORMATS = array(
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tif', 'jpe', 'heic'
    );
    // 支持的文档格式
    private const DOCUMENT_FORMATS = array(
        'psd', 'psb', 'eps', 'ai', 'pdf',
        // 'doc', 'docx', 'ppt', 'pptx',
    );
    // 支持的相机RAW格式
    private const RAW_FORMATS = array(
        'dng', 'cr2', 'erf', 'raf', 'kdc', 'dcr', 'mrw', 'nrw', 'nef', 'orf', 'pef',
        'x3f', 'srf', 'arw', 'sr2', '3fr', 'crw', 'dcm', 'fff', 'iiq', 'mdc', 'mef',
        'mos', 'plt', 'ppm', 'raw', 'rw2', 'srw', 'tst'
    );
    // 所有支持格式
    private $allFormats = array();

    private $plugin;
    private $defQuality = 85;
    private $defFormat = 'jpeg';
    private $maxResolution = 40000; // 支持的最大分辨率

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->allFormats = array_merge(
            self::IMAGE_FORMATS, 
            self::DOCUMENT_FORMATS, 
            self::RAW_FORMATS
        );

        $this->setTmpDir();
        $this->setMemLimit();
    }

    // 设置Imagick临时目录
    private function setTmpDir() {
        if(!is_dir(TEMP_FILES)){mk_dir(TEMP_FILES);}
        $path = TEMP_FILES . '/imagick'; mk_dir($path);
        putenv('MAGICK_TEMPORARY_PATH='.$path);
        putenv('MAGICK_TMPDIR='.$path);
    }

    // 设置Imagick内存限制——实际是ImageMagick在占用系统内存，不受PHP内存限制
    private function setMemLimit() {
        $memFree = $this->getMemLimit();
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, $memFree);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, $memFree * 2);
    }
    // 获取系统内存限制
    private function getMemLimit() {
        $memBase = 128 * 1024 * 1024; // 128M，最小内存限制——实际可用内存可能更小，暂不处理
		$memFree = $this->plugin->sysMemoryFree();
		return max($memBase, intval($memFree * 0.5));
    }

    // 格式是否支持
    public function isSupport($ext) {
        return in_array(strtolower($ext), $this->allFormats);
    }

    /**
     * 图片生成缩略图
     * @param [type] $file
     * @param [type] $cacheFile
     * @param [type] $maxSize
     * @param [type] $ext
     * @return void
     */
    public function createThumb($file, $cacheFile, $maxSize, $ext) {
        if (!file_exists($file) || !$this->isSupport($ext)) return false;

        $this->setMemLimit(); // 内存限制
        $imagick = null;

        try {
            $imagick = new Imagick();

            // 预读图像尺寸
            $imagick->pingImage($file);
            $orgWidth   = $imagick->getImageWidth();
            $orgHeight  = $imagick->getImageHeight();
            $imagick->clear();  // 清除ping结果
            // 限制超大图片
            if ($orgWidth > $this->maxResolution || $orgHeight > $this->maxResolution) {
                $msg = sprintf("Imagick convert error [%s]: Image too large: %s x %s", $file, $orgWidth, $orgHeight);
                $this->log($msg);
                return false;
            }
            // 启用像素缓存加速
            // $imagick->setOption('temporary-path', '/dev/shm');
            $imagick->setOption('cache:sync', '0');  // 禁用同步写入
            $imagick->setOption('sampling-factor', '4:2:0');  // 色度子采样
            $imagick->setOption('filter:blur', '0.8');        // 轻微模糊提升缩放速度

            // 读取图像
            if (in_array($ext, self::DOCUMENT_FORMATS)) {
                // 特殊格式处理
                $imagick->setResolution(300, 300);
                $imagick->readImage($file . '[0]');
            } else if (in_array($ext, self::RAW_FORMATS)) {
                // RAW格式处理
                $imagick->setResolution(300, 300);
                $imagick->readImage($file);
                $this->correctImageOrientation($imagick);
            } else if ($ext === 'gif' || $ext === 'tif') {
                // 多帧图像处理
                $imagick->readImage($file . '[0]');
            } else {
                // 超大图片，读取后立即缩小
                $maxReadSize = 5000; // 预读最大尺寸
                if ($orgWidth > $maxReadSize || $orgHeight > $maxReadSize) {
                    $ratio = min($maxReadSize / $orgWidth, $maxReadSize / $orgHeight);
                    $readWidth = intval($orgWidth * $ratio);
                    $readHeight = intval($orgHeight * $ratio);
                    // $imagick->sampleImage($readWidth, $readHeight);  // 先降采样
                    $imagick->readImage($file . '[' . $readWidth . 'x' . $readHeight . ']');
                    $imagick->scaleImage($readWidth, $readHeight, true); // 缩放
                } else {
                    // 普通图像
                    $imagick->readImage($file);
                }
            }

            // 获取实际图像（处理多页文档）
            $image = $imagick->getImage();

            // 自动旋转校正
            $this->autoOrientImage($image);
            // 颜色空间转换
            if ($image->getImageColorspace() == Imagick::COLORSPACE_CMYK) {
                $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            }

            // 保持宽高比缩放
            $image->thumbnailImage($maxSize, 0);
            // $ratio = $maxSize / $orgWidth; // 缩略图的宽度比例
            // $newHeight = (int)($orgHeight * $ratio);
            // $image->resizeImage($maxSize, $newHeight, Imagick::FILTER_LANCZOS, 1);
            // $image->thumbnailImage($maxSize, 0, true, false); // 报错

            // 处理透明背景（当输出格式为JPEG时）
            if ($this->defFormat === 'jpeg' && $image->getImageAlphaChannel()) {
                $thumbWidth  = $image->getImageWidth();
                $thumbHeight = $image->getImageHeight();
                $background = new Imagick();
                $background->newImage(
                    $thumbWidth, 
                    $thumbHeight, 
                    'white',
                    'jpeg'
                );
                $background->compositeImage(
                    $image, 
                    Imagick::COMPOSITE_OVER, 
                    0, 0
                );
                $image = $background;
            }

            // 设置输出格式和质量
            $image->setImageFormat($this->defFormat);
            
            if ($this->defFormat === 'png') {
                // PNG压缩级别：0-9 (0=无压缩, 9=最大压缩)
                $compressionLevel = min(9, max(0, round(9 - ($this->defQuality / 10))));
                $image->setOption('png:compression-level', $compressionLevel);

                $image->setOption('png:exclude-chunk', 'all'); // 移除所有辅助块
                $image->setOption('png:compression-strategy', '1'); // 更快策略
            } else {
                $image->setImageCompressionQuality($this->defQuality);
            }

            // 移除元数据
            $image->stripImage();

            // 写入文件
            $result = $image->writeImage($cacheFile);
            return $result;
        } catch (Exception $e) {
            // Stack trace: $e->getTraceAsString()
            $msg = sprintf("Imagick convert error [%s]: %s", $file, $e->getMessage());
            $this->log($msg);
            return false;
        } finally {
            // 确保资源释放
            if ($imagick instanceof Imagick) {
                $imagick->clear();
                $imagick->destroy();
            }
        }
    }
    
    /**
     * 自动校正图像方向
     */
    private function autoOrientImage(Imagick $image) {
        $orientation = $image->getImageOrientation();
        
        switch ($orientation) {
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $image->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $image->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $image->rotateImage('#000', -90);
                break;
        }
        
        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }
    
    /**
     * 校正RAW格式图像方向
     */
    private function correctImageOrientation(Imagick $image) {
        try {
            // 尝试获取EXIF方向信息
            $exif = $image->getImageProperties('exif:*');
            if (isset($exif['exif:Orientation'])) {
                $orientation = (int)$exif['exif:Orientation'];
                $image->setImageOrientation($orientation);
                $this->autoOrientImage($image);
            }
        } catch (Exception $e) {
            // 忽略EXIF读取错误
        }
    }
    
    /**
     * 获取图片信息，类getimagesize格式
     */
    public function getImgSize($file, $ext = '') {
        if (!file_exists($file)) return false;

        $imagick = null;
        try {
            $imagick = new Imagick();
            // 特殊格式只读第一页/第一帧
            if (in_array($ext, self::DOCUMENT_FORMATS) || $ext === 'gif' || $ext === 'tif') {
                $imagick->pingImage($file . '[0]');
            } else {
                $imagick->pingImage($file); // readImage，使用pingImage避免加载像素
            }
            // $image = $imagick->getImage();
            return array (
                $imagick->getImageWidth(),
                $imagick->getImageHeight(),
                // 'channels'  => $image->getImageChannelCount(),
                'channels'  => 3,
                'bits'      => $imagick->getImageDepth(),
            );
        } catch (Exception $e) {
            return false;
        } finally {
            if ($imagick instanceof Imagick) {
                $imagick->clear();
                $imagick->destroy();
            }
        }
    }

    // 记录日志
    public function log($msg) {
        $this->plugin->log($msg);
    }
    
}
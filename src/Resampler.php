<?php
/**
 * Part of Resampler.php - simple image resampler
 * 
 * @author Pavel@kutac.cz
 * @version 1.0
 */

namespace Resampler;

/**
 * Main Resampler class
 */
class Resampler{
    /**
     * Path to file
     * @var string
     */
    protected $file;
    /**
     * Image width
     * @var int
     */
    protected $width;
    /**
     * Image height
     * @var int
     */
    protected $height;
    /**
     * Mime type of image
     * @var string
     */
    protected $mimeType;
    /**
     * Mime type in constant IMAGETYPE_xxx
     * @var int
     */
    protected $mimeTypeConstant;
    /**
     * Resource object for image
     * @var resource
     */
    protected $imgResource= null;
    /**
     * Image background for rectangle or crop or PNG with transparent
     * @var array
     */
    protected $bgColor = array(0xff,0xff,0xff,127);

    /**
     * When origin size is smaller than wanted, image will not be resampled.
     * In case of crop or rectangle original file is returned, if both sizes are smaller.
     * @var int
     */
    const FORCE_SIZE_TYPE_NONE = 1;
    /**
     * Works only with crop and rectangle and is default for both methods.
     * Original image will be centered, rest filled with background color, @see setBgColor()
     * @var int
     */
    const FORCE_SIZE_TYPE_CENTER = 2;
    /**
     * When origin size is smaller than wanted, image will be scaled up
     * @var int
     */
    const FORCE_SIZE_TYPE_SCALE_UP = 3;
    /**
     * Use resize for size changing
     * @var string
     */
    const RESAMPLE_TYPE_RESIZE = "resize";
    /**
     * Use crop for size changing
     * @var string
     */
    const RESAMPLE_TYPE_CROP = "crop";
    /**
     * Make rectangle and origin image put inside
     * @var string
     */
    const RESAMPLE_TYPE_RECTANGLE = "rectangle";
    /**
     * Rotation clockwise direction
     * @var string
     */
    const ROTATE_CW = "cw";
    /**
     * Rotation counterclockwise direction
     * @var string
     */
    const ROTATE_CCW = "ccw";
    /**
     * Rotation by 180 degrees
     * @var string
     */
    const ROTATE_180 = "180";

    /**
     * Create new object and load informations about it
     * @param string $file       Path to image
     * @param resource $resource Resource of already loaded image
     */
    protected function __construct($file, $resource = null){
        if ($resource !== null && is_resource($resource)) {
            $this->imgResource = $resource;
        }
        $this->file = $file;
        if ($resource !== null) {
            return;
        }
        if(!is_readable($file)){
            throw new FileException("Can't read file ", $file);
        }
        $this->loadImageData();
    }

    /**
     * Destruct for releasing memory
     */
    public function __destruct(){
        $this->releaseMemory();
    }

    /**
     * Load information about picture
     */
    protected function loadImageData(){
        // getImageSize is GD function
        $info = getimagesize($this->file);
        if($info == false){
            throw new FileException("Can't read file as image", $file);
        }
        list($this->width, $this->height, $this->mimeTypeConstant) = $info;
        $this->mimeType = $info['mime'];
    }  

    /**
     * Create new object and load information about it
     * @param  string $file Path to image
     * @return Resampler instance
     */
    public static function load($file){
        if (!extension_loaded('gd')) {
            throw new Exception("GD extension is not installed. Please install GD first");
        }
        return new static($file);
    }

    /**
     * Load image content to memory, if isn't
     * @param bool $force Load again from original file
     * @return Resampler instance
     */
    public function loadToMemory($force = false){
        if($this->imgResource != null && $force){
            $this->releaseMemory();
        }
        if($this->imgResource != null){
            return $this;
        }
        if($this->availableMemory() == false){
            throw new OutOfMemoryException("Cannot load image to memory, there isn't enough space");
        }
        $this->loadImageData();
        switch ($this->mimeTypeConstant){
            case IMAGETYPE_PNG:
                $this->imgResource = imagecreatefrompng($this->file);
                break;
            case IMAGETYPE_GIF:
                $this->imgResource = imagecreatefromgif($this->file);
                break;
            case IMAGETYPE_JPEG:
            case IMAGETYPE_JPEG2000:
                $this->imgResource = imagecreatefromjpeg($this->file);
                break;
            default:
                throw new FileException("Image can be only PNG, GIF or JPEG", $this->file);
        }

        if($this->imgResource === false){
            throw new OutOfMemoryException("Cannot load image to memory");
        }
        imagealphablending( $this->imgResource, true );
        imagesavealpha( $this->imgResource, true );
        return $this;
    }

    /**
     * Check if there is enough space in memory for this picture
     * @param int $width Width of image, if null use main image width
     * @param int $height Height of image, if null use main image height
     * @param int $imgIntMimeType IMAGETYPE_XXX constant of image
     * @param bool $saving Counting memory for saving.. Only extra space is needed
     * @return boolean
     */
    protected function availableMemory($width = null, $height = null, $imgIntMimeType = null, $saving = false){
        $val = trim(ini_get("memory_limit"));
        $last = strtolower($val[strlen($val)-1]);
        $val = intval($val);
        // There is no memory limit
        if ($val <= 0) {
            return true;
        }
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        if($width == null){ $width = $this->width; }
        if($height == null){ $height = $this->height; }
        if($imgIntMimeType == null){ $imgIntMimeType = $this->mimeTypeConstant; }

        $memoryToUse = $width * $height; // Memory which image will use
        if ($saving) {
            // We need extra space for saving PNG
            // Space is same as during loading, but now we have allocated
            // memory for image resource from GD library. Only difference will be extra used
            if ($imgIntMimeType === IMAGETYPE_PNG) {
                $memoryToUse *= 9.2 - 5.2;
            }
        }else{
            switch ($imgIntMimeType){
                case IMAGETYPE_PNG:
                    $memoryToUse *= 9.2;
                    break;
                case IMAGETYPE_GIF:
                    $memoryToUse *= 2.4;
                    break;
                default:
                    $memoryToUse *= 5.2;
            }
        }
        if( $memoryToUse > ($val - memory_get_usage(true))){
            return false;
        }
        return true;
    }

    /**
     * Release image from memory
     * @return Resampler instance
     */
    public function releaseMemory(){
        if($this->imgResource != null){
            imagedestroy($this->imgResource);
            $this->imgResource = null;
        }
        return $this;
    }

    /**
     * Get width and height in HTML format to put into <img> tag
     * @return string
     */
    public function getCurrentHTMLSize(){
        return 'width="'.$this->width.'" height="'.$this->height.'"';
    }

    /**
     * Get width and height in HTML format to put into <img> tag after resampling
     * @param int $maxWidth Final width of image, may be lower in case force is false
     * @param int $maxHeight Final height of image, may be lower in case force is false
     * @param string $resampleType Type of final image, see Resampler::RESAMPLE_TYPE_xxx
     * @param int $forceSizeType Action with smaller images than final size, see Resampler::FORCE_SIZE_TYPE_xxx
     * @return string
     */
    public function getResampledHTMLSize($maxWidth, $maxHeight, $resampleType, $forceSizeType = 0){
        $newSize = static::getResizeParams($this->width, $this->height, $maxWidth, $maxHeight, $resampleType, $forceSizeType);
        if($newSize !== false){
            return 'width="'.$newSize['pane-width'].'" height="'.$newSize['pane-height'].'"';
        }
        return "";
    }

    /**
     * Get information for copyimageresample function
     * @param int $originWidth Original width of image
     * @param int $originHeight Original height of image
     * @param int $maxWidth Final width of image, may be lower in case force is false
     * @param int $maxHeight Final height of image, may be lower in case force is false
     * @param string $resampleType Type of final image, see Resampler::RESAMPLE_TYPE_xxx
     * @param int $forceSizeType Action with smaller images than final size, see Resampler::FORCE_SIZE_TYPE_xxx
     * @return boolean|array False for unsupported $resampleType, array otherwise
     *  - width - Width of image, can be lower than width of pane
     *  - height - Height of image, can be lower than height of pane
     *  - pane-width - Final width of pane
     *  - pane-height - Final height of pane
     *  - src-x - x-coordinate of source point
     *  - src-y - y-coordinate of source point
     *  - dst-x - x-coordinate of destination point
     *  - dst-y - y-coordinate of destination point
     *  - different - Final image will be different than source
     */
    public static function getResizeParams($originWidth, $originHeight, $maxWidth, $maxHeight, $resampleType, $forceSizeType = 0){
        $ratio = $originHeight / $originWidth;  
        $newSize = array(
            'width' => $originWidth,
            'height' => $originHeight,
            'pane-width' => $originWidth,
            'pane-height' => $originHeight,
            'src-x' => 0,
            'src-y' => 0,
            'dst-x' => 0,
            'dst-y' => 0,
            'resample_type' => $resampleType,
            'different' => true
            );
        // Both dimensions are smaller than origin and do not scale up, or is set center for resize which is invalid for that type
        // Or wanted dimension is the same as source
        if($originWidth < $maxWidth && $originHeight < $maxHeight && ($forceSizeType == self::FORCE_SIZE_TYPE_NONE || $forceSizeType == self::FORCE_SIZE_TYPE_CENTER && $resampleType == "resize")
            || $originWidth == $maxWidth && $originHeight == $maxHeight){
            $newSize['different'] = false;
            return $newSize;
        }
        $newSize['width'] = $maxWidth;
        $newSize['height'] = round($maxWidth * $ratio);
        $newSize['pane-width'] = $maxWidth;
        $newSize['pane-height'] = $maxHeight;

        switch ($resampleType){
            case self::RESAMPLE_TYPE_CROP :{
                if($newSize['height'] < $maxHeight){
                    $newSize['height'] = $maxHeight;
                    $newSize['width'] = round($maxHeight / $ratio);
                }
                if($originWidth < $newSize['pane-width'] || $originHeight < $newSize['pane-height']){
                    if ($forceSizeType === self::FORCE_SIZE_TYPE_CENTER || $forceSizeType == null) {
                        $newSize['height'] = $originHeight;
                        $newSize['width'] = $originWidth;
                    }
                    if ($forceSizeType === self::FORCE_SIZE_TYPE_NONE) {
                        $newSize['pane-width'] = min($originWidth, $maxWidth);
                        $newSize['pane-height'] = min($originHeight, $maxHeight);
                    }
                }
                $newSize['dst-x'] = 0 - ($newSize['width'] - $newSize['pane-width']) / 2;
                $newSize['dst-y'] = 0 - ($newSize['height'] - $newSize['pane-height']) / 2;
                break;
            }
            case self::RESAMPLE_TYPE_RESIZE:
            case self::RESAMPLE_TYPE_RECTANGLE:{
                if($newSize['height'] > $maxHeight){
                    $newSize['height'] = $maxHeight;
                    $newSize['width'] = $maxHeight / $ratio;          
                }
                if($resampleType == self::RESAMPLE_TYPE_RECTANGLE){
                    if($originWidth <= $maxWidth && $originHeight <= $maxHeight &&
                        ($forceSizeType === self::FORCE_SIZE_TYPE_CENTER || $forceSizeType == null)){
                        $newSize['height'] = $originHeight;
                        $newSize['width'] = $originWidth;
                    }else if(($originWidth <= $newSize['pane-width'] || $originHeight <= $newSize['pane-height']) &&
                        $forceSizeType === self::FORCE_SIZE_TYPE_NONE){
                        $newSize['pane-width'] = $newSize['width'];
                        $newSize['pane-height'] = $newSize['height'];
                    }
                    $newSize['dst-x'] = ($newSize['pane-width'] - $newSize['width']) / 2;
                    $newSize['dst-y'] = ($newSize['pane-height'] - $newSize['height']) / 2;
                }else{
                    $newSize['pane-width'] = $newSize['width'];
                    $newSize['pane-height'] = $newSize['height'];
                }
                break;
            }
            default:
                return false;
        }
        return $newSize;
    }

    /**
     * Get string mime type of image
     * @return string
     */
    public function getMimeType(){
        return $this->mimeType;
    }

    /**
     * Get actual width of image
     * @return number
     */
    public function getWidth(){
        return $this->width;
    }

    /**
     * Get actual height of image
     * @return number
     */
    public function getHeight(){
        return $this->height;
    }

    /**
     * Check if image will be changed, or resampling method will be ignored
     * @param int $maxWidth Final width of image, may be lower in case force is false
     * @param int $maxHeight Final height of image, may be lower in case force is false
     * @param string $resampleType Type of final image, see Resampler::RESAMPLE_TYPE_xxx
     * @param int $forceSizeType Action with smaller images than final size, see Resampler::FORCE_SIZE_TYPE_xxx
     * @return boolean
     */
    public function willChangeSize($maxWidth, $maxHeight, $resampleType, $forceSizeType = 0){
        $ret = static::getResizeParams($this->width, $this->height, $maxWidth, $maxHeight, $resampleType, $forceSizeType);
        if(isset($ret['different'])){
            return $ret['different'];
        }
        return false;
    }

    /**
     * Create second pane for resampling
     * @param int $width Pane width
     * @param int $height Pane height
     * @return resource|false Return resource or false if there is error
     */
    protected function createTmbImg($width, $height){
        // ImageCreateTrueColor will use the same amount of memory as loading JPEG
        if (!$this->availableMemory($width, $height, IMAGETYPE_JPEG)) {
            throw new OutOfMemoryException("Cannot create pane for resampling, there isn't enough free memory");
        }
        $img = ImageCreateTrueColor($width, $height);

        if($img == false){
            throw new OutOfMemoryException("Unable to allocate place for thumb pane");
        }    
        imagealphablending( $img, false );
        imagesavealpha( $img, true );
        $bg  = imagecolorallocatealpha($img, $this->bgColor[0], $this->bgColor[1], $this->bgColor[2], $this->bgColor[3]);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);
        imagealphablending( $img, true );

        return $img;
    }

    /**
     * Resize picture to maximum width and height. Original loaded picture is rewrited if $returnNewCanvas is false
     * @param int $maxWidth Maximum width
     * @param int $maxHeight Maximum height
     * @param bool $scaleUp If source image is smaller, will be scaled up
     * @param bool $returnNewCanvas If true, new instance of Resampler is returned and original can be used again
     * @return Resampler instance
     */
    public function resize($maxWidth, $maxHeight, $scaleUp = false, $returnNewCanvas = false){
        return $this->resample(self::RESAMPLE_TYPE_RESIZE, $maxWidth, $maxHeight, $scaleUp ? self::FORCE_SIZE_TYPE_SCALE_UP : self::FORCE_SIZE_TYPE_NONE, $returnNewCanvas);
    }

    /**
     * Crop picture to width and height. Original loaded picture is rewrited if $returnNewCanvas is false
     * @param int $width Width of final image
     * @param int $height Height of final image
     * @param int $forceSizeType Action with smaller images than final size, @see Resampler::FORCE_SIZE_TYPE_xxx
     * @param bool $returnNewCanvas If true, new instance of Resampler is returned and original can be used again
     * @return Resampler instance
     */
    public function crop($width, $height, $forceSizeType = null, $returnNewCanvas = false){
        return $this->resample(self::RESAMPLE_TYPE_CROP, $width, $height, $forceSizeType, $returnNewCanvas);
    }

    /**
    * Create rectangle and put source image to center. Original loaded picture is rewrited if $returnNewCanvas is false
    * @param int $width - Width of final image
    * @param int $height - Height of final image
    * @param int $forceSizeType - Action with smaller images than final size, @see Resampler::FORCE_SIZE_TYPE_xxx
    * @param bool $returnNewCanvas If true, new instance of Resampler is returned and original can be used again
    * @return Resampler instance
    */
    public function rectangle($width, $height, $forceSizeType = null, $returnNewCanvas = false){
        return $this->resample(self::RESAMPLE_TYPE_RECTANGLE, $width, $height, $forceSizeType, $returnNewCanvas);
    }

    /**
     * Redraw image to canvas with same size
     * @return Resampler instance
     */
    public function forceRedraw()
    {
        return $this->resample("redraw", null, null);
    }

    /**
     * Proceed resampling for type and size
     * @param string $type
     * @param int $width
     * @param int $height
     * @param bool $returnNewCanvas If true, new instance of Resampler is returned and original can be used again
     * @return Resampler
     */
    protected function resample($type, $width, $height, $forceSizeType = null, $returnNewCanvas = false){
        if ($type === "redraw") {
            $newSize = array(
                'width' => $this->width,
                'height' => $this->height,
                'pane-width' => $this->width,
                'pane-height' => $this->height,
                'src-x' => 0,
                'src-y' => 0,
                'dst-x' => 0,
                'dst-y' => 0
            );
        }else{
            $newSize = static::getResizeParams($this->width, $this->height, $width, $height, $type, $forceSizeType);
            if($newSize['different'] === false){
                return $this;
            }
        }
        $this->loadToMemory();
        $tmb = $this->createTmbImg($newSize['pane-width'], $newSize['pane-height']);

        imagecopyresampled($tmb, $this->imgResource, $newSize['dst-x'], $newSize['dst-y'], $newSize['src-x'], $newSize['src-y'], $newSize['width'], $newSize['height'], $this->width, $this->height);

        $returnObject = $this;
        if ($returnNewCanvas) {
            $returnObject = new static($this->file, $tmb);
            $returnObject->mimeType = $this->mimeType;
            $returnObject->mimeTypeConstant = $this->mimeTypeConstant;
            $returnObject->bgColor = $this->bgColor;
        }

        $returnObject->width = $newSize['pane-width'];
        $returnObject->height = $newSize['pane-height'];

        if (!$returnNewCanvas) {
            $returnObject->releaseMemory();
            $returnObject->imgResource = $tmb;
        }

        return $returnObject;
    }

    /**
     * Rotate canvas with given angle
     * @param  string|int  $angle       Angle and direction to rotate, possible values are 90, -90 180 or @see ROTATE_xxx
     * @param  boolean $returnNewCanvas If true, new instance of Resampler is returned and original can be used again
     * @return Resampler instance
     */
    public function rotate($angle, $returnNewCanvas = false){
        $this->loadToMemory();
        if (!$this->availableMemory($this->width, $this->height, IMAGETYPE_JPEG)) {
            throw new OutOfMemoryException("Cannot create pane for rotation, there isn't enough free memory");
        }
        $angles = array(self::ROTATE_CW => -90, self::ROTATE_CCW => 90, self::ROTATE_180 => 180);
        if (in_array($angle, $angles, true)) {
            $angleNumeric = $angle;
        }else{
            $angleNumeric = (isset($angles[$angle])) ? $angles[$angle] : null;            
        }

        if (!$angleNumeric) {
            throw new Exception("Undefined angle for rotation, use predefined constants");
        }
        $tmb = imagerotate($this->imgResource, $angleNumeric, imagecolorallocatealpha($this->imgResource, $this->bgColor[0], $this->bgColor[1], $this->bgColor[2], $this->bgColor[3]));
        imagealphablending( $tmb, true );
        imagesavealpha( $tmb, true );

        $returnObject = $this;
        if ($returnNewCanvas) {
            $returnObject = new static($this->file, $tmb);
            $returnObject->mimeType = $this->mimeType;
            $returnObject->mimeTypeConstant = $this->mimeTypeConstant;
            $returnObject->bgColor = $this->bgColor;
            $returnObject->height = $this->height;
            $returnObject->width = $this->width;
        }

        if ($angle == self::ROTATE_CW or $angle == self::ROTATE_CCW) {
            $w = $this->width;
            $h = $this->height;
            $returnObject->height = $w;
            $returnObject->width = $h;
        }

        if (!$returnNewCanvas) {
            $returnObject->releaseMemory();
            $returnObject->imgResource = $tmb;
        }

        return $returnObject;
    }

    /**
     * Set background color for case of transparent PNG or rectangle and center crop
     * @param string $color Color in format #rgb, #rrggbb, #rrggbbaa or transparent
     * @return Resampler instance
     */
    public function setBgColor($color){
        if ($color === "transparent") {
            $this->bgColor = array(255, 255, 255, 127);
            return $this;
        }
        if(preg_match("/^#([0-9a-f]{3})(?:([0-9a-f]{3})([0-9a-f]{2})?)?$/i", $color, $matches)){
            if(empty($matches[2])){
                $this->bgColor[0] = hexdec($color[1].$color[1]);
                $this->bgColor[1] = hexdec($color[2].$color[2]);
                $this->bgColor[2] = hexdec($color[3].$color[3]);
            }else{
                $this->bgColor[0] = hexdec($color[1].$color[2]);
                $this->bgColor[1] = hexdec($color[3].$color[4]);
                $this->bgColor[2] = hexdec($color[5].$color[6]);
            }
            if(empty($matches[3])){
                $this->bgColor[3] = 0;
            }else{
                $this->bgColor[3] = intval(hexdec($color[7].$color[8]) / 2);
            }
        }else{
            throw new Exception("Color {$color} is in bad format");
        }
        return $this;
    }

    /**
     * Get mime type in format IMAGETYPE_XXX from filename
     * @param string $file Name of file
     * @return int|boolean
     */
    protected static function getTypeFromExtension($file){
        $file = strtolower($file);
        $dotPos = strrpos($file, ".");
        if ($dotPos === false) {
            return false;
        }
        $ext = substr($file, $dotPos + 1);
        switch ($ext){
            case "jpg":
            case "jpeg":
                return IMAGETYPE_JPEG;
            case "png":
                return  IMAGETYPE_PNG;
            case "gif":
                return IMAGETYPE_GIF;
            default:
                return false;
        }
    }
    /**
     * Return path to file, where image will be saved
     * If $file is empty, path to original file is returned
     * If $file ends with / or \, original filename is added to destination folder
     * If $file does not contain suffix (jpg, png or gif) this is added from original file
     * Otherwise, passed path is returned
     * @param  string $file Path or partial path ro file
     * @return string       Return absolute path to file
     */
    public function getPathWithSuffix($file = null){
        if (empty($file)) {
            return $this->file;
        }
        if (in_array($file[strlen($file) - 1], array("/", "\\"))) {
            return $file.basename($this->file);
        }
        $mimeType = static::getTypeFromExtension($file);
        if ($mimeType) {
            return $file;
        }
        switch ($this->mimeTypeConstant) {
            case IMAGETYPE_PNG:{
                $file .= ".png";
                break;
            }
            case IMAGETYPE_GIF:{
                $file .= ".gif";
                break;
            }
            default:{
                $file .= ".jpg";
                break;
            }
        }
        return $file;
    }

    /**
     * Save image to location
     * @param string $file Path to new file, if empty original is over written
     * @param int $quality Picture quality. For PNG 0-9, for JPG 0-100
     * @return Resampler instance
     */
    public function save($file = null, $quality = null){
        $file = $this->getPathWithSuffix($file);
        $mimeType = static::getTypeFromExtension($file);
        if($mimeType === false){
            throw new FileException("Unsupported image format", $file);
        }
        if((file_exists($file) && !is_writable($file)) || (!file_exists($file) && !is_writable(dirname($file)))){
            throw new FileException("Can't write into file", $file);
        }
        $this->loadToMemory();
        if (!$this->availableMemory(null, null, $mimeType, true)) {
            throw new OutOfMemoryException("Cannot save image to file, there isn't enough needed memory");
        }
        switch ($mimeType){
            case IMAGETYPE_PNG:
                if($quality > 9 || $quality < 0 || $quality === null){
                    $quality = 9;
                }
                $success = imagepng($this->imgResource, $file, $quality);
                break;
            case IMAGETYPE_GIF:
                $success = imagegif($this->imgResource, $file);
                break;
            default:
                if($quality > 100 || $quality < 0 || $quality === null){
                    $quality = 85;
                }
                $success = imagejpeg($this->imgResource, $file, $quality);
        }
        if($success === false){
            throw new FileException("Cannot save image to location", $file);
        }
        return $this;
    }

    /**
     * Send image to browser with headers if $sendHeader is true
     * @param string $ext Type of image: jpg, png or gif
     * @param int $quality Picture quality. For PNG 0-9, for JPG 0-100
     * @param bool $sendHeader If true, image header will be sent
     * @return Resampler instance
     */
    public function output($ext = null, $quality = null, $sendHeader = true){
        $this->loadToMemory();
        $mimeType = $this->mimeTypeConstant;
        if($ext != null){
            $mimeType = static::getTypeFromExtension(".".$ext);
            if($mimeType === false){
                throw new Exception("Unsupported image extension");
            }
        }
        if (!$this->availableMemory(null, null, $mimeType, true)) {
            throw new OutOfMemoryException("Cannot stream image, there isn't enough needed memory");
        }
        switch ($mimeType){
            case IMAGETYPE_PNG:{
                if($quality > 9 || $quality < 0 || $quality === null){
                    $quality = 9;
                }
                if ($sendHeader) {
                    header("Content-type: image/png");
                }
                $success = imagepng($this->imgResource, null, $quality);
                break;
            }
            case IMAGETYPE_GIF:{
                if ($sendHeader) {
                    header("Content-type: image/gif");
                }
                $success = imagegif($this->imgResource, null);
                break;
            }
            default:{
                if ($sendHeader) {
                    header("Content-type: image/jpeg");
                }
                if($quality > 100 || $quality < 0 || $quality === null){
                    $quality = 85;
                }
                $success = imagejpeg($this->imgResource, null, $quality);
            }
        }
        if($success === false){
            throw new Exception("Cannot send image");
        }
        return $this;
    }
}
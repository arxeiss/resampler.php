# Resampler.php

Simple PHP class for resampling images with GD library.

### Why another  library? What is so special?

* Resampler is only for resampling and never will contain drawing methods
* Counting memory usage to avoid Fatal error caused by allocating too much memory
* Support *resize*, *crop*, *rectangle* and *rotate* methods. Especially *rectangle* is missing in many other libraries
* Using cascade pattern / method chaining
* Support JPEG, PNG and GIF images. Decision is automatical based on file suffix

### Simple code examples

All public methods are commented and there is no documentation now. Check code examaples and source code.

```php
try{
    $img = Resampler::load("path/to/file.jpg")  // Load given file
        ->resize(300, 200)                      // Resize to fit maximum size 300x200 px
        ->rotate(Resampler::ROTATE_CW)          // Perform clockwise rotation by 90°
        ->save("path/to/thumb.jpg")             // Save file as JPEG file
        ->output("jpg");                        // Send also as output to browser as JPEG file
}catch(Exception $e){
    die($e->getMessage());
}
```

### Generated image examples with code

#### Chaining resize and rectangle
Image is loaded and resized to fit maximum size **300** x **220** px and saved.  
The same image is inserted into rectangle with size **350** x **250** px and background color. Image inside is resized up due to parameter FORCE_SIZE_TYPE_SCALE_UP.
```php
// Step 1
Resampler::load("img/backup.jpg")->resize(300, 220)->save("step1.jpg")
// Step 2
->setBgColor("#aa2222")->rectangle(350, 250, Resampler::FORCE_SIZE_TYPE_SCALE_UP)->save("step2.jpg");
```

![Example 1](http://www.kutac.cz/image/resampler-example-1-92416.png)

#### Multiple output images from same original
Image is loaded and background color is set. First method of next each line (except last one) return new instance of Resampler (last parametr is `true`). Because of this, in `$img` is still original big bitmap.

Saving here shows different methods of saving. Name is added if missing, or only suffix. Otherwise, original is replaced by new file.

```php
$img = Resampler::load("img/logo.png")->setBgColor("#48b3f6AA"); 
// saved as img/cropped/logo.png
$img->crop(400, 200, null, true)->rotate(Resampler::ROTATE_CW)
    ->save("img/cropped/");
// saved as img/rectangle.png
$img->rotate(Resampler::ROTATE_CCW, true)->setBgColor("#7DF64866")
    ->rectangle(400, 400)->save("img/rectangle");
// Saved into original file
$img->resize(400, 400)->save("");
```
![Example 2](http://www.kutac.cz/image/resampler-example-2-39548.png)

#### Image base64 output
Last parametr of output deciding to send or not to send headers. Now headers are not send and with output buffer we can "catch" and encode data to base64. We are using also other public functions to get mime type and image size.
```php
$img = Resampler::load("img/flying.jpg")->setBgColor("#dd0")->crop(200, 200)->rectangle(220, 220);
$htmlSize = $img->getCurrentHTMLSize();
$mimetypeImg = $img->getMimeType();
ob_start();
$img->output(null, null, false);
$baseImg = base64_encode(ob_get_clean());
echo '<body>
    <img src="data:'.$mimetypeImg.';base64,'.$baseImg.'" '.$htmlSize.'>
</body>'
```

**Output:**
```html
<body>
    <img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQA...." width="220" height="220">
</body>
```
![Example 3](http://www.kutac.cz/image/resampler-example-3-09390.jpg)
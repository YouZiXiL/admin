<?php

namespace plugin\admin\app\controller;

use Exception;
use Intervention\Image\ImageManagerStatic as Image;
use plugin\admin\app\controller\Base;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\Upload;
use support\exception\BusinessException;
use support\Request;
use support\Response;

/**
 * 附件管理 
 */
class UploadController extends Crud
{
    /**
     * 不需要鉴权的方法
     */
    public $noNeedAuth = ['file', 'image'];
    
    /**
     * @var Upload
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new Upload;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view("upload/index");
    }

    /**
     * 查询
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function select(Request $request): Response
    {
        [$where, $format, $page_size, $field, $order] = $this->selectInput($request);
        if (!empty($where['ext']) && is_string($where['ext'])) {
            $where['ext'] = ['in', explode(',', $where['ext'])];
        }
        $query = $this->doSelect($where, $field, $order);
        return $this->doFormat($query, $format, $page_size);
    }

    /**
     * 插入
     * @param Request $request
     * @return Response
     */
    public function insert(Request $request): Response
    {
        return view("upload/insert");
    }

    /**
     * 更新
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function update(Request $request): Response
    {
        if ($request->method() === 'GET') {
            return view("upload/update");
        }
        return parent::update($request);
    }

    /**
     * 上传文件
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function file(Request $request): Response
    {
        $file = current($request->file());
        if (!$file || !$file->isValid()) {
            return $this->json(1, '未找到文件');
        }
        $img_exts = [
            'jpg',
            'jpeg',
            'png',
            'gif'
        ];
        if (in_array($file->getUploadExtension(), $img_exts)) {
            return $this->image($request);
        }
        $data = $this->base($request, '/upload/files/'.date('Ymd'));
        return $this->json(0, '上传成功', [
            'path' => $data['path'],
            'name' => $data['name'],
            'size' => $data['size'],
        ]);
    }

    /**
     * 上传附件
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function attachment(Request $request): Response
    {
        if ($request->method() === 'GET') {
            return view('upload/attachment');
        }
        $file = current($request->file());
        if (!$file || !$file->isValid()) {
            return $this->json(1, '未找到文件');
        }
        $data = $this->base($request, '/upload/files/'.date('Ymd'));
        $upload = new Upload;
        $upload->admin_id = admin_id();
        $upload->name = $data['name'];
        [
            $upload->url,
            $upload->name,
            $_,
            $upload->file_size,
            $upload->mime_type,
            $upload->image_width,
            $upload->image_height,
            $upload->ext
        ] = array_values($data);
        $upload->category = $request->post('category');
        $upload->save();
        return $this->json(0, '上传成功', [
            'path' => $data['path'],
            'name' => $data['name'],
            'size' => $data['size'],
        ]);
    }

    /**
     * 上传头像
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function avatar(Request $request): Response
    {
        $file = current($request->file());
        if ($file && $file->isValid()) {
            $ext = strtolower($file->getUploadExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'gif', 'png'])) {
                return json(['code' => 2, 'msg' => '仅支持 jpg jpeg gif png格式']);
            }
            $image = Image::make($file);
            $width = $image->width();
            $height = $image->height();
            $size = min($width, $height);
            $relative_path = 'upload/avatar/' . date('Ym');
            $real_path = base_path() . "/plugin/admin/public/$relative_path";
            if (!is_dir($real_path)) {
                mkdir($real_path, 0777, true);
            }
            $name = bin2hex(pack('Nn',time(), random_int(1, 65535)));
            $ext = $file->getUploadExtension();

            $image->crop($size, $size)->resize(300, 300);
            $path = base_path() . "/plugin/admin/public/$relative_path/$name.lg.$ext";
            $image->save($path);

            $image->resize(120, 120);
            $path = base_path() . "/plugin/admin/public/$relative_path/$name.md.$ext";
            $image->save($path);

            $image->resize(60, 60);
            $path = base_path() . "/plugin/admin/public/$relative_path/$name.$ext";
            $image->save($path);

            $image->resize(30, 30);
            $path = base_path() . "/plugin/admin/public/$relative_path/$name.sm.$ext";
            $image->save($path);

            return json([
                'code' => 0,
                'msg' => '上传成功',
                'data' => [
                    'path' => "/app/admin/$relative_path/$name.$ext"
                ]
            ]);
        }
        return json(['code' => 1, 'msg' => 'file not found']);
    }

    /**
     * 上传图片
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function image(Request $request): Response
    {
        $data = $this->base($request, '/upload/img/'.date('Ymd'));
        $realpath = $data['realpath'];
        try {
            $img = Image::make($realpath);
            $max_height = 1170;
            $max_width = 1170;
            $width = $img->width();
            $height = $img->height();
            $ratio = 1;
            if ($height > $max_height || $width > $max_width) {
                $ratio = $width > $height ? $max_width / $width : $max_height / $height;
            }
            $img->resize($width*$ratio, $height*$ratio)->save($realpath);
        } catch (Exception $e) {
            unlink($realpath);
            return json( [
                'code'  => 500,
                'msg'  => '处理图片发生错误'
            ]);
        }
        return json( [
            'code'  => 0,
            'msg'  => '上传成功',
            'data' => [
                'path' => $data['path'],
                'name' => $data['name'],
                'size' => $data['size'],
            ]
        ]);
    }

    /**
     * 获取上传数据
     * @param Request $request
     * @param $relative_dir
     * @return array
     * @throws BusinessException
     */
    protected function base(Request $request, $relative_dir): array
    {
        $relative_dir = ltrim($relative_dir, '/');
        $file = current($request->file());
        if (!$file || !$file->isValid()) {
            throw new BusinessException('未找到上传文件', 400);
        }

        $base_dir = base_path() . '/plugin/admin/public/';
        $full_dir = $base_dir . $relative_dir;
        if (!is_dir($full_dir)) {
            mkdir($full_dir, 0777, true);
        }

        $ext = strtolower($file->getUploadExtension());
        $ext_forbidden_map = ['php', 'php3', 'php5', 'css', 'js', 'html', 'htm', 'asp', 'jsp'];
        if (in_array($ext, $ext_forbidden_map)) {
            throw new BusinessException('不支持该格式的文件上传', 400);
        }

        $relative_path = $relative_dir . '/' . bin2hex(pack('Nn',time(), random_int(1, 65535))) . ".$ext";
        $full_path = $base_dir . $relative_path;
        $file_size = $file->getSize();
        $file_name = $file->getUploadName();
        $mime_type = $file->getUploadMimeType();
        $file->move($full_path);
        $image_with = $image_height = 0;
        if ($img_info = getimagesize($full_path)) {
            [$image_with, $image_height] = $img_info;
            $mime_type = $img_info['mime'];
        }
        return [
            'path'     => "/app/admin/$relative_path",
            'name'     => $file_name,
            'realpath' => $full_path,
            'size'     => $file_size,
            'mime_type' => $mime_type,
            'image_with' => $image_with,
            'image_height' => $image_height,
            'ext' => $ext,
        ];
    }

    /**
     * 格式化文件大小
     * @param $file_size
     * @return string
     */
    protected function formatSize($file_size): string
    {
        $size = sprintf("%u", $file_size);
        if($size == 0) {
            return("0 Bytes");
        }
        $size_name = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
        return round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $size_name[$i];
    }

}

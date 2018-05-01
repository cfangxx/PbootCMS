<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date  2017年12月15日
 *  文章控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\ContentModel;

class ContentController extends Controller
{

    private $model;

    private $blank;

    public function __construct()
    {
        $this->model = new ContentModel();
    }

    // 文章列表
    public function index()
    {
        if ((! ! $id = get('id', 'int')) && $result = $this->model->getContent($id)) {
            $this->assign('more', true);
            $this->assign('content', $result);
        } else {
            $this->assign('list', true);
            if (! $mcode = get('mcode', 'var')) {
                error('传递的模型编码参数有误，请核对后重试！');
            }
            
            if (isset($_GET['keyword'])) {
                $result = $this->model->findContent($mcode, get('scode'), get('keyword'));
            } else {
                $result = $this->model->getList($mcode);
            }
            $this->assign('contents', $result);
            
            // 文章分类下拉列表
            $sort_model = model('admin.content.ContentSort');
            $sort_select = $sort_model->getListSelect($mcode);
            $this->assign('search_select', $this->makeSortSelect($sort_select, get('scode')));
            $this->assign('sort_select', $this->makeSortSelect($sort_select));
            $this->assign('subsort_select', $this->makeSortSelect($sort_select));
            
            // 模型名称
            $this->assign('model_name', model('admin.content.Model')->getName($mcode));
            
            // 扩展字段
            $this->assign('extfield', model('admin.content.ExtField')->getModelField($mcode));
        }
        
        $this->display('content/content.html');
    }

    // 文章增加
    public function add()
    {
        if ($_POST) {
            
            // 获取数据
            $scode = post('scode');
            $subscode = post('subscode');
            $title = post('title');
            $titlecolor = post('titlecolor');
            $subtitle = post('subtitle');
            $filename = post('filename');
            $author = post('author');
            $source = post('source');
            $outlink = post('outlink');
            $date = post('date');
            $ico = post('ico');
            $pics = post('pics');
            $content = post('content');
            $enclosure = post('enclosure');
            $keywords = post('keywords');
            $description = post('description');
            $status = post('status', 'int');
            $istop = post('istop', 'int');
            $isrecommend = post('isrecommend', 'int');
            $isheadline = post('isheadline', 'int');
            
            if (! $scode) {
                alert_back('内容分类不能为空！');
            }
            
            if (! $title) {
                alert_back('文章标题不能为空！');
            }
            
            // 检查自定义文件名称
            if ($filename) {
                while ($this->model->checkFilename("filename='$filename'")) {
                    $filename = $filename . '_' . mt_rand(1, 20);
                }
            }
            
            // 构建数据
            $data = array(
                'acode' => session('acode'),
                'scode' => $scode,
                'subscode' => $subscode,
                'title' => $title,
                'titlecolor' => $titlecolor,
                'subtitle' => $subtitle,
                'filename' => $filename,
                'author' => $author,
                'source' => $source,
                'outlink' => $outlink,
                'date' => $date,
                'ico' => $ico,
                'pics' => $pics,
                'content' => $content,
                'enclosure' => $enclosure,
                'keywords' => $keywords,
                'description' => $description,
                'sorting' => 255,
                'status' => $status,
                'istop' => $istop,
                'isrecommend' => $isrecommend,
                'isheadline' => $isheadline,
                'visits' => 0,
                'likes' => 0,
                'oppose' => 0,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 执行添加
            if (! ! $id = $this->model->addContent($data)) {
                // 扩展内容添加
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'ext_') === 0) {
                        if (! isset($data2['contentid'])) {
                            $data2['contentid'] = $id;
                        }
                        $temp = post($key);
                        if (is_array($temp)) {
                            $data2[$key] = implode(',', $temp);
                        } else {
                            $data2[$key] = str_replace("\r\n", '<br>', $temp);
                        }
                    }
                }
                if (isset($data2)) {
                    if (! $this->model->addContentExt($data2)) {
                        $this->model->delContent($id);
                        $this->log('新增文章失败！');
                        error('新增失败！', - 1);
                    }
                }
                
                $this->log('新增文章成功！');
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', $backurl);
                } else {
                    success('新增成功！', url('/admin/Content/index/mcode/' . get('mcode')));
                }
            } else {
                $this->log('新增文章失败！');
                error('新增失败！', - 1);
            }
        } else {
            $this->assign('add', true);
            
            if (! $mcode = get('mcode', 'var')) {
                error('传递的模型编码参数有误，请核对后重试！');
            }
            
            // 文章分类
            $sort_model = model('admin.content.ContentSort');
            $sort_select = $sort_model->getListSelect($mcode);
            $this->assign('sort_select', $this->makeSortSelect($sort_select));
            $this->assign('subsort_select', $this->makeSortSelect($sort_select));
            
            // 扩展字段
            $this->assign('extfield', model('admin.content.ExtField')->getModelField($mcode));
            
            $this->display('content/content.html');
        }
    }

    // 生成分类选择
    private function makeSortSelect($tree, $selectid = null)
    {
        $list_html = '';
        foreach ($tree as $value) {
            // 默认选择项
            if ($selectid == $value->scode) {
                $select = "selected='selected'";
            } else {
                $select = '';
            }
            $list_html .= "<option value='{$value->scode}' $select>{$this->blank}{$value->name}";
            // 子菜单处理
            if ($value->son) {
                $this->blank .= '　　';
                $list_html .= $this->makeSortSelect($value->son, $selectid);
            }
        }
        // 循环完后回归位置
        $this->blank = substr($this->blank, 0, - 6);
        return $list_html;
    }

    // 文章删除
    public function del()
    {
        // 执行批量删除
        if ($_POST) {
            if (! ! $list = post('list')) {
                if ($this->model->delContentList($list)) {
                    $this->log('批量删除文章成功！');
                    success('批量删除成功！', - 1);
                } else {
                    $this->log('批量删除文章失败！');
                    error('批量删除失败！', - 1);
                }
            } else {
                error('请选择要删除的内容！', - 1);
            }
        }
        
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($this->model->delContent($id)) {
            $this->model->delContentExt($id);
            $this->log('删除文章' . $id . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除文章' . $id . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 文章修改
    public function mod()
    {
        if (! ! $submit = post('submit')) {
            switch ($submit) {
                case 'sorting': // 修改列表排序
                    $listall = post('listall');
                    if ($listall) {
                        $sorting = post('sorting');
                        foreach ($listall as $key => $value) {
                            if ($sorting[$key] === '' || ! is_numeric($sorting[$key]))
                                $sorting[$key] = 255;
                            $this->model->modContent($value, "sorting=" . $sorting[$key]);
                        }
                        $this->log('修改内容排序成功！');
                        success('修改成功！', - 1);
                    } else {
                        alert_back('排序失败，无任何内容！');
                    }
                    break;
            }
            exit();
        }
        
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 单独修改状态
        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($this->model->modContent($id, "$field='$value',update_user='" . session('username') . "'")) {
                location(- 1);
            } else {
                alert_back('修改失败！');
            }
        }
        
        // 修改操作
        if ($_POST) {
            
            // 获取数据
            $scode = post('scode');
            $subscode = post('subscode');
            $title = post('title');
            $titlecolor = post('titlecolor');
            $subtitle = post('subtitle');
            $filename = post('filename');
            $author = post('author');
            $source = post('source');
            $outlink = post('outlink');
            $date = post('date');
            $ico = post('ico');
            $pics = post('pics');
            $content = post('content');
            $enclosure = post('enclosure');
            $keywords = post('keywords');
            $description = post('description');
            $status = post('status', 'int');
            $istop = post('istop', 'int');
            $isrecommend = post('isrecommend', 'int');
            $isheadline = post('isheadline', 'int');
            
            if (! $scode) {
                alert_back('内容分类不能为空！');
            }
            
            if (! $title) {
                alert_back('文章标题不能为空！');
            }
            
            if ($filename) {
                while ($this->model->checkFilename("filename='$filename' and id<>$id")) {
                    $filename = $filename . '_' . mt_rand(1, 20);
                }
            }
            
            // 构建数据
            $data = array(
                'scode' => $scode,
                'subscode' => $subscode,
                'title' => $title,
                'titlecolor' => $titlecolor,
                'subtitle' => $subtitle,
                'filename' => $filename,
                'author' => $author,
                'source' => $source,
                'outlink' => $outlink,
                'date' => $date,
                'ico' => $ico,
                'pics' => $pics,
                'content' => $content,
                'enclosure' => $enclosure,
                'keywords' => $keywords,
                'description' => $description,
                'status' => $status,
                'istop' => $istop,
                'isrecommend' => $isrecommend,
                'isheadline' => $isheadline,
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->modContent($id, $data)) {
                // 扩展内容修改
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'ext_') === 0) {
                        $temp = post($key);
                        if (is_array($temp)) {
                            $data2[$key] = implode(',', $temp);
                        } else {
                            $data2[$key] = str_replace("\r\n", '<br>', $temp);
                        }
                    }
                }
                if (isset($data2)) {
                    if ($this->model->findContentExt($id)) {
                        $this->model->modContentExt($id, $data2);
                    } else {
                        $data2['contentid'] = $id;
                        $this->model->addContentExt($data2);
                    }
                }
                
                $this->log('修改文章' . $id . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', $backurl);
                } else {
                    success('修改成功！', url('/admin/Content/index'));
                }
            } else {
                location(- 1);
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getContent($id)) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('content', $result);
            
            if (! $mcode = get('mcode', 'var')) {
                error('传递的模型编码参数有误，请核对后重试！');
            }
            
            // 文章分类
            $sort_model = model('admin.content.ContentSort');
            $sort_select = $sort_model->getListSelect($mcode);
            $this->assign('sort_select', $this->makeSortSelect($sort_select, $result->scode));
            $this->assign('subsort_select', $this->makeSortSelect($sort_select, $result->subscode));
            
            // 扩展字段
            $this->assign('extfield', model('admin.content.ExtField')->getModelField($mcode));
            
            $this->display('content/content.html');
        }
    }
}
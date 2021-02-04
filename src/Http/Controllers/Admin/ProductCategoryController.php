<?php

namespace Flex360\Pilot\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Jzpeepz\Dynamo\Dynamo;
use Jzpeepz\Dynamo\FieldGroup;
use Jzpeepz\Dynamo\Http\Controllers\DynamoController;
use Flex360\Pilot\Facades\Product as ProductFacade;
use Flex360\Pilot\Facades\ProductCategory as ProductCategoryFacade;

class ProductCategoryController extends DynamoController
{
    public function getDynamo()
    {
        $dynamo = Dynamo::make(get_class(ProductCategoryFacade::getFacadeRoot()))

                    /***********************************************************************************
                     *  Pilot plugin: Product Category form view                                       *
                     *  Check the plugins 'fields' array and attach the fields to the dynamo object    *
                     ************************************************************************************/
                    ->addFormHeaderButton(function() {
                        return '<a href="/pilot/productcategory" class="btn btn-info btn-sm">Back to Product Categories</a>';
                    })
                    ->addFormHeaderButton(function() {
                        return '<a href="/pilot/product?view=published" class="btn btn-primary btn-sm">Back to Products</a>';
                    })
                    ->removeBoth('deleted_at');
                    if (config('pilot.plugins.products.children.manage_product_categories.fields.title', true)) {
                        $dynamo->text('title', [
                            'class' => 'category-name-for-delete-modal',
                        ]);
                    }
                    if (config('pilot.plugins.products.children.manage_product_categories.fields.featured_image', true)) {
                        $dynamo->singleImage('featured_image', [
                            'help' => 'Upload photo. Once selected, hover over the image and select the edit icon (paper & pencil) to manage metadata title, photo credit, and description.',
                            'maxWidth' => 1000,
                        ]);
                    }
                    if (config('pilot.plugins.products.children.manage_product_categories.fields.product_selector', true)) {
                        $dynamo->hasMany('products', [
                            'options' => ProductFacade::all()->pluck('name', 'id'),
                            'label' => 'Products',
                            'class' => 'category-dual-list',
                            'id' => 'category-dual-list',
                            'tooltip' => 'Select the Products you would like to belong to this category.',
                        ]);
                    }
                    
                    /************************************************************************************
                     *  Pilot plugin: Product Category index view                                      *
                     *  Check the plugins 'fields' array and set the index view for this module        *
                     ************************************************************************************/
                    $dynamo->addIndexButton(function() {
                        return '<a href="/pilot/product?view=published" class="btn btn-primary btn-sm">Back to Products</a>';
                    })
                    ->applyScopes()
                    ->paginate(10);

                    if (config('pilot.plugins.products.children.manage_product_categories.fields.featured_image', true)) {
                        $dynamo->addIndex('featured_image', 'Featured Image', function ($item) {
                            if (empty($item->featured_image__thumb)) {
                                return '';
                            }
                            return '<img style="width: 100px  " src="' . $item->featured_image__thumb . '" class="" style="width: 60px;">';
                        });
                    }
                    if (config('pilot.plugins.products.children.manage_product_categories.fields.title', true)) {
                        $dynamo->addIndex('title');
                    }
                    if (config('pilot.plugins.products.children.manage_product_categories.fields.product_sort_method') == 'manual_sort') {
                        $dynamo->addIndex('id', 'Order Products in this Category',function ($item) {
                            return '<a href="' . route('admin.productcategory.products', ['id' => $item->id]) . '" class="btn btn-success">Order</a>';
                        });
                    }

                    $dynamo->addIndex('test', 'Number of Product\'s in this category', function($item) {
                        return $item->products->count();
                    });
                    $dynamo->addIndex('updated_at', 'Last Edited');
                    
                    $dynamo->addActionButton(function($item) {
                        return '<a href="'.$item->url().'" target="_blank"  class="btn btn-secondary btn-sm">View</a>';
                    })
                    ->addActionButton(function($item) {
                        return '<a href="productcategory/' . $item->id . '/copy"  class="btn btn-secondary btn-sm">Copy</a>';
                    })
                    ->hideDelete()
                    ->addFormFooterButton(function() {
                        return '<a href="/pilot/testing" class="mt-3 btn btn-danger btn" data-toggle="modal" data-target="#relationships-manager-modal">Delete</a>';
                    })
                    ->indexOrderBy('title');

        return $dynamo;
    }


    /**
     * Copy the Product Category
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function copy($id)
    {
        $productCategory = ProductCategoryFacade::find($id);

        $newProductCategory = $productCategory->duplicate();

        // set success message
        \Session::flash('alert-success', 'Category copied successfully!');

        return redirect()->route('admin.productcategory.edit', array($newProductCategory->id));
    }

    /**
    * Remove the specified resource in storage.
    *
    * @param  int  $id
    * @return Response
    */
    public function destroy($id)
    {
        $category = ProductCategoryFacade::find($id);

        $category->delete();

        // set success message
        \Session::flash('alert-success', 'Category deleted successfully!');

        return \Redirect::route('admin.productcategory.index');
    }


    /**
     * Returns a view where admin can see and reorder faqs within this category
     *
     * @return View
     */
    public function products($id)
    {
        $productcategory = ProductCategoryFacade::find($id);
        
        $items = $productcategory->products()->orderBy(config('pilot.table_prefix') . 'product_' . config('pilot.table_prefix') . 'product_category.position')->get();

        $dynamo = (new ProductController)->getDynamo();

        return view('pilot::admin.dynamo.products.reorder', compact('dynamo', 'items', 'productcategory'));
    }

    /**
     * Functions runs on 'reorder' of Products within this category
     *
     * @return View
     */
    public function reorderProductsWithinCategory($id)
    {
        $productcategory = ProductCategoryFacade::find($id);

        $ids = request()->input('ids');

        foreach ($ids as $position => $productID) {
            $product = ProductFacade::find($productID);
            $product->product_categories()->updateExistingPivot($productcategory->id, compact('position'));
        }

        return $ids;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\ImportErrorsExport;
use App\Exports\ProductsExport;
use App\Exports\ProductsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductExportRequest;
use App\Http\Requests\Admin\ProductImportRequest;
use App\Models\Product;
use App\Models\ProductImportRun;
use App\Services\ProductImport\ProductImportService;
use App\Support\ProductFilters;
use App\Support\ProductSheet\ProgressTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Product spreadsheet import / export for the admin panel.
 *
 * Every action is gated by an ability from config/aroma.php (admin.abilities)
 * — enforced on the route AND here, so the rule can't be lost by a routing
 * change. See docs/product-import-export/DEVELOPER.md.
 */
class ProductImportExportController extends Controller
{
    /** @var ProductImportService */
    private $imports;

    /** @var ProgressTracker */
    private $progress;

    public function __construct(ProductImportService $imports, ProgressTracker $progress)
    {
        $this->imports = $imports;
        $this->progress = $progress;
    }

    /* Export & template ------------------------------------------------------ */

    /** @return BinaryFileResponse|RedirectResponse */
    public function export(ProductExportRequest $request)
    {
        $this->authorize('products.export');

        $scope = $request->input('scope', 'all');
        $query = Product::query();

        if ($scope === 'selected') {
            $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));

            if ($ids === []) {
                return redirect()->route('admin.products.index')->with('error', __('admin.products_io.nothing_selected'));
            }

            $query->whereIn('id', $ids);
        } elseif ($scope === 'filtered') {
            ProductFilters::apply($query, $request->only(['q', 'category', 'active']));
        }

        Log::channel('imports')->info('product export', [
            'user_id' => $request->user()->id,
            'scope'   => $scope,
            'rows'    => (clone $query)->count(),
            'filters' => $scope === 'filtered' ? $request->only(['q', 'category', 'active']) : null,
        ]);

        return Excel::download(new ProductsExport($query), 'aroma-products-'.now()->format('Ymd-His').'.xlsx');
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('products.template');

        Log::channel('imports')->info('product template downloaded', ['user_id' => auth()->id()]);

        return Excel::download(new ProductsTemplateExport(), 'aroma-products-template.xlsx');
    }

    /* Import ------------------------------------------------------------------ */

    public function create(): View
    {
        $this->authorize('products.import');

        return view('admin.products.import.create', [
            'maxMb'  => (int) config('aroma.import.max_upload_mb', 10),
            'queue'  => (string) config('aroma.import.queue', 'imports'),
        ]);
    }

    public function store(ProductImportRequest $request): RedirectResponse
    {
        $this->authorize('products.import');

        $run = $this->imports->createRun($request->user(), $request->file('file'), $request->only(['auto_apply', 'replace_images', 'ignore_image_failures']));
        $this->imports->start($run);

        return redirect()->route('admin.products.import.show', $run)->with('status', __('admin.products_io.uploaded'));
    }

    public function show(ProductImportRun $run): View
    {
        $this->authorize('products.import.history');

        return view('admin.products.import.show', [
            'run'      => $run->load('user'),
            'progress' => $this->progress->snapshot($run),
            'queue'    => (string) config('aroma.import.queue', 'imports'),
            'shown'    => 200,
        ]);
    }

    /** Polled by the progress bar while a run is active. */
    public function status(ProductImportRun $run): JsonResponse
    {
        $this->authorize('products.import.history');

        return response()->json($this->progress->snapshot($run));
    }

    public function confirm(ProductImportRun $run): RedirectResponse
    {
        $this->authorize('products.import');

        if ($run->canConfirm()) {
            $this->imports->confirm($run);
            $this->imports->log('confirmed by admin', $run->fresh(), ['confirmed_by' => auth()->id()]);
        }

        return redirect()->route('admin.products.import.show', $run)->with('status', __('admin.products_io.confirmed'));
    }

    public function cancel(ProductImportRun $run): RedirectResponse
    {
        $this->authorize('products.import');

        $this->imports->cancel($run);

        return redirect()->route('admin.products.import.show', $run)->with('status', __('admin.products_io.discarded'));
    }

    public function errors(ProductImportRun $run): BinaryFileResponse
    {
        $this->authorize('products.import.history');

        return Excel::download(new ImportErrorsExport($this->imports->allErrors($run)), 'import-'.$run->id.'-errors.xlsx');
    }

    public function history(): View
    {
        $this->authorize('products.import.history');

        return view('admin.products.import.index', [
            'runs' => ProductImportRun::query()->with('user')->latest('id')->paginate(20),
        ]);
    }
}

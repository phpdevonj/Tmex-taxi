<?php

namespace App\DataTables;

use App\Models\Subscription;
use App\Traits\DataTableTrait;
use DateTime;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use NumberFormatter;

class SubscriptionDataTable extends DataTable
{
    use DataTableTrait;
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)           
            ->editColumn('Name', function ($row) {
                return ucfirst($row->name) ?? '';
            })
            
            ->filterColumn('name', function( $query, $keyword ){
                $query->where('name', 'like' , '%'.$keyword.'%');                    
            })
            
            ->editColumn('price' , function ( $row ) {
                $currency = strtoupper($row->currency ?? 'USD');
                $amount = $row->price ?? 0;
            
                // Ensure amount is in major currency units (e.g., 5000 → 50.00)
                if ($amount > 1000) {
                    $amount = $amount / 100; 
                }
                return  number_format($amount, 2). ' '. $currency ;
            })

            ->editColumn('Interval' , function ( $row ) {
                    return $row->interval ;
            })

            ->addColumn('Stripe Price ID', function ($row) {
                return $row->stripe_price_id ;
            })
            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->addIndexColumn()
            ->addColumn('action', function($subscription){
                $id = $subscription->id;
                return view('subscription.action',compact('subscription','id'))->render();
            })
            ->order(function ($query) {
                if (request()->has('order')) {
                    $order = request()->order[0];
                    $column_index = $order['column'];

                    $column_name = 'created_at';
                    $direction = 'desc';
                    if( $column_index != 0) {
                        $column_name = request()->columns[$column_index]['data'];
                        $direction = $order['dir'];
                    }
    
                    $query->orderBy($column_name, $direction);
                }
            })
            ->rawColumns([ 'action' ]);
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\SubscriptionDataTable $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(Subscription $model)
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    protected function getColumns()
    {
        return [
            Column::make('DT_RowIndex')
                ->searchable(false)
                ->title(__('message.srno'))
                ->orderable(false)
                ->width(60),
            Column::make('name')->title( __('message.name') ),
            Column::make('price')->title( __('message.price') ),
            Column::make('interval')->title( __('message.interval') ),
            Column::make('stripe_price_id')->title( __('message.stripe_price_id') )->orderable(false),
            Column::make('created_at')->title( __('message.created_at') ),
            Column::computed('action')
                  ->exportable(false)
                  ->printable(false)
                  ->width(60)
                  ->addClass('text-center'),
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'Subscription_' . date('YmdHis');
    }
}

<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

class HeadStyles
{
    public static function render(): HtmlString
    {
        $css = '
            <style>
                .exporting .fi-pagination,
                .exporting .fi-header-actions-ctn {
                    display: none !important;
                }
                .fi-fo-toggle-buttons.fi-btn-group {
                    background-color: rgb(243 244 246) !important;
                    padding: 4px !important;
                    border-radius: 0.5rem !important;
                    border: none !important;
                    gap: 0 !important;
                    display: flex !important;
                    width: 100% !important;
                }

                .fi-modal-header {
                    align-items: center !important;
                    padding-top: 1rem !important;
                    padding-bottom: 1rem !important;
                }

                .fi-modal-header > div {
                    display: flex !important;
                    align-items: center !important;
                }

                .fi-modal-content {
                    padding-top: 0.75rem !important;
                }

                .fi-modal-close-btn {
                    margin-top: -5px !important;
                }

                .fi-fo-toggle-buttons.fi-btn-group .fi-btn {
                    background-color: transparent !important;
                    border: none !important;
                    box-shadow: none !important;
                    color: rgb(107 114 128) !important;
                    padding: 0.625rem 1rem !important;
                    font-size: 0.875rem !important;
                    border-radius: 0.375rem !important;
                    flex: 1 1 0% !important;
                    text-align: center !important;
                    justify-content: center !important;
                }

                .fi-fo-toggle-buttons.fi-btn-group input:checked + .fi-btn {
                    background-color: white !important;
                    color: rgb(217 119 6) !important;
                    box-shadow: 0 1px 2px rgba(0,0,0,0.08) !important;
                    font-weight: 600 !important;
                }

                .fi-modal-window .fi-simple-page-content,
                [x-show="! notifications.length"] {
                    text-align: center !important;
                }

                [x-show="! notifications.length"] p {
                    display: block !important;
                    margin-top: 0.25rem !important;
                }
            </style>
        ';

        if (request()->is('*/transactions*', '*/customers*', '*/users*', '*/audit-logs*')) {
            $css .= '
                <style>
                    .fi-ta-header-toolbar {
                        flex-direction: row-reverse !important;
                        justify-content: flex-end !important;
                        gap: 0.75rem;
                    }

                    .fi-ta-header-toolbar > * {
                        margin-inline-start: 0 !important;
                    }
                </style>
            ';
        }

        if (request()->is('*/customers/*')) {
            $css .= '
                <style>
                    .fi-breadcrumbs-item-label {
                        display: inline-flex !important;
                        align-items: center !important;
                        gap: 0.375rem !important;
                    }

                    .fi-breadcrumbs-item-label::before {
                        content: "";
                        display: inline-block;
                        width: 1rem;
                        height: 1rem;
                        background-image: url("data:image/svg+xml,%3Csvg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"2\" stroke=\"%236b7280\"%3E%3Cpath stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M15.75 19.5L8.25 12l7.5-7.5\" /%3E%3C/svg%3E");
                        background-repeat: no-repeat;
                        background-size: contain;
                    }
                </style>
            ';
        }

        return new HtmlString($css);
    }
}

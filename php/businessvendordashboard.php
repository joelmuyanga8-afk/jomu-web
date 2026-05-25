<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard</title>
    <link rel="stylesheet" href="/assets/bootstrap.css">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        html,
        body.vendor-dashboard-page,
        body.vendor-dashboard-page > header {
            margin: 0;
            padding: 0;
        }

        body.vendor-dashboard-page {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            padding: 0;
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }

        body.vendor-dashboard-page.account-suspended > main,
        body.vendor-dashboard-page.account-suspended > footer {
            filter: blur(6px);
            pointer-events: none;
            user-select: none;
        }

        body.vendor-dashboard-page.account-suspended .vendor-dashboard-suspended-disabled {
            pointer-events: none;
            opacity: 0.55;
            cursor: not-allowed;
        }

        .vendor-suspension-overlay {
            position: fixed;
            inset: 0;
            z-index: 12000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: rgba(17, 17, 17, 0.42);
            pointer-events: auto;
        }

        .vendor-suspension-panel {
            width: min(520px, 100%);
            background: rgba(255, 255, 255, 0.96);
            border-radius: 12px;
            border-top: 5px solid rgb(241, 90, 36);
            padding: 22px 20px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
            text-align: center;
        }

        .vendor-suspension-panel h2 {
            margin: 0 0 10px;
            font-size: 1.2rem;
            font-weight: 800;
        }

        .vendor-suspension-panel p {
            margin: 0 0 8px;
            color: #4b5563;
            line-height: 1.45;
        }

        body.vendor-dashboard-page > footer {
            margin-top: auto;
        }

        body.vendor-dashboard-page > header {
            position: relative;
            padding-top: 62px;
        }

        #navbarone {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 62px;
            min-height: 62px;
            padding: 0 18px;
            padding-top: 0;
            padding-bottom: 0;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.08);
            margin: 0;
            z-index: 1030;
        }

        body.vendor-dashboard-page.account-suspended #navbarone {
            z-index: 13000;
        }

        body.vendor-dashboard-page.account-suspended #navbarone .dropdown-menu,
        body.vendor-dashboard-page.account-suspended #navbarone .offcanvas {
            z-index: 13001;
        }

        body.vendor-dashboard-page.account-suspended .modal-backdrop.show {
            z-index: 13990;
        }

        body.vendor-dashboard-page.account-suspended #signOutConfirmModal {
            z-index: 14000;
        }

        #dashboard-secondary-nav {
            margin: 0;
            padding: 0;
            padding-top: 6px;
        }

        @media (max-width: 767.98px) {
            #dashboard-secondary-nav .containerone-vendordashboard {
                display: flex;
                flex-wrap: nowrap;
                gap: 8px;
                margin-left: 6px;
                margin-right: 6px;
            }

            #dashboard-secondary-nav .containerone-vendordashboard .dropdown {
                flex: 0 0 auto;
            }

            #dashboard-secondary-nav .dashboard-nav-btn {
                white-space: nowrap;
            }
        }

        @media (max-width: 420px) {
            #dashboard-secondary-nav .containerone-vendordashboard {
                gap: 4px;
                margin-left: 2px;
                margin-right: 2px;
            }

            #dashboard-secondary-nav .dashboard-nav-btn {
                padding-left: 8px;
                padding-right: 8px;
            }
        }

        #navbarone .vendor-dashboard-brand-wrap {
            width: auto;
            margin: 0;
            padding: 0;
            flex: 0 0 auto;
        }

        #navbarone .vendor-dashboard-brand-wrap .navbar-brand {
            margin: 0;
            padding: 0;
            display: inline-flex;
            align-items: center;
        }

        #navbarone .vendor-dashboard-brand-wrap .brand-logos {
            margin: 0;
            line-height: 0;
        }

        #navbarone .vendor-dashboard-brand-wrap .logo {
            width: 124px;
        }

        #navbarone .vendor-dashboard-brand-wrap .brand-logos:hover .logo {
            opacity: 1 !important;
        }

        #navbarone .vendor-dashboard-title-wrap {
            flex: 1 1 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            margin: 0;
            padding: 0 12px;
        }

        #navbarone .vendor-dashboard-title {
            margin: 0;
            color: rgb(0, 0, 255);
            font-size: clamp(1.12rem, 2vw, 1.75rem);
            font-weight: 800;
            line-height: 1.1;
            white-space: nowrap;
            text-align: center;
        }

        #navbarone .navbar-toggler {
            position: static;
            margin: 0;
            padding: 0;
            border: 0;
            border-radius: 12px;
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            box-shadow: none !important;
        }

        #navbarone .navbar-toggler:focus {
            box-shadow: none;
        }

        #navbarone .navbar-toggler .options-icons {
            width: 20px;
            height: 20px;
            margin: 0;
        }

        .dashboard-mobile-auth-dropdown {
            position: relative;
            flex: 0 0 auto;
        }

        .dashboard-mobile-auth-trigger {
            border: 0;
            background: transparent;
            width: 42px;
            height: 42px;
            padding: 0;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none !important;
        }

        .dashboard-mobile-auth-trigger:focus {
            box-shadow: none;
            outline: none;
        }

        .dashboard-mobile-auth-icon {
            width: 36px;
            height: 36px;
            object-fit: contain;
            display: block;
        }

        #navbarone #navbarNav {
            margin-left: auto;
            flex: 0 0 auto;
            align-items: center;
        }

        .vendor-dashboard-page .footer-links {
            gap: 4px 8px;
        }

        .dashboard-nav-btn {
            position: relative;
            padding-right: 16px;
        }

        .dashboard-tab-badge {
            position: absolute;
            top: -6px;
            right: 0;
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            background: rgb(241, 90, 36);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 1;
            padding: 0 5px;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .dashboard-tab-badge.visible {
            display: inline-flex;
        }

        .showroom-container .showroom-img .video-wrapper {
            padding-top: 0;
            height: 400px;
        }

        #dashboardMediaPreviewOverlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
        }

        #dashboardMediaPreviewOverlay.active {
            display: flex;
        }

        .dashboard-media-preview-content {
            max-width: 96vw;
            max-height: 72vh;
            width: auto;
            height: auto;
            object-fit: contain;
            background: #000;
        }

        .dashboard-media-preview-panel {
            max-width: 96vw;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .dashboard-media-preview-details {
            width: min(96vw, 620px);
            background: rgba(9, 9, 9, 0.82);
            color: #fff;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: left;
        }

        .dashboard-media-preview-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.3;
            color: rgb(241, 90, 36);
        }

        .dashboard-media-preview-price,
        .dashboard-media-preview-description {
            margin: 4px 0 0;
            font-size: 0.9rem;
            line-height: 1.35;
        }

        .dashboard-media-preview-close {
            position: absolute;
            top: 14px;
            right: 16px;
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 34px;
            line-height: 1;
            cursor: pointer;
            padding: 2px 8px;
        }

        #dashboard-listings .dots-icon {
            cursor: pointer;
            margin-top: 30px;
        }

        #dashboard-listings .card-options .dropdown-content {
            top: 74px !important;
        }

        #dashboard-listings .dropdown:hover .dropdown-content {
            display: none;
        }

        #dashboard-listings .dropdown.is-open .dropdown-content {
            display: block;
            animation: slideDown 0.25s;
        }

        #dashboard-purchases .dashboard-purchases-shell > h5,
        #dashboard-purchases #purchase-empty-state {
            text-align: center;
        }

        #dashboard-listings .add-listing-card-wrap {
            padding-left: 4px;
            padding-right: 4px;
            max-width: 180px;
            margin-left: auto;
            margin-right: auto;
        }

        #dashboard-listings .add-listing-card {
            width: 100%;
            max-width: 160px;
            height: auto;
            border-radius: 8px;
            border: 1px solid black;
            overflow: hidden;
        }

        #dashboard-listings .add-listing-card .card-img-top,
        #dashboard-listings .add-listing-card .add-listing-card-icon {
            display: block;
            width: auto;
            max-width: 72%;
            max-height: 56px;
            height: auto !important;
            margin: 0.35rem auto 0.1rem;
            padding: 0;
            object-fit: contain;
            opacity: 0.4;
            pointer-events: none;
        }

        #dashboard-listings .add-listing-card .add-listing-card-caption {
            margin: 0;
            border: 0;
            border-top: 1px solid #000;
            border-radius: 0 0 8px 8px;
            padding: 0.2rem 0.35rem 0.35rem;
        }

        #dashboard-listings .add-listing-card .add-listing-card-label {
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.15;
            font-weight: 600;
        }

        #dashboard-listings .owner-hidden-card {
            position: relative;
            overflow: hidden;
        }

        #dashboard-listings .owner-hidden-card .media-preview-source,
        #dashboard-listings .owner-hidden-card video {
            filter: blur(5px) grayscale(0.45);
            opacity: 0.62;
            pointer-events: none;
        }

        #dashboard-listings .owner-hidden-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 4;
            padding: 4px 10px;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0;
        }

        #dashboard-listings .dropdown-content .li-1 {
            padding-right: 0;
        }

        #dashboard-listings .dropdown-content .li-1::after {
            display: none;
        }

        #deleteListingModal .modal-header {
            justify-content: center;
            position: relative;
        }

        #deleteListingModal .modal-title {
            width: 100%;
            text-align: center;
        }

        #deleteListingModal .btn-close {
            position: absolute;
            right: 1rem;
        }

        #deleteListingModal .modal-footer {
            justify-content: center;
        }

        #declineRequestModal .modal-header {
            justify-content: center;
            position: relative;
        }

        #declineRequestModal .modal-title {
            width: 100%;
            text-align: center;
            font-weight: 800;
        }

        #declineRequestModal .btn-close {
            position: absolute;
            right: 1rem;
        }

        #declineRequestModal .modal-body p {
            margin: 0;
            font-size: 0.98rem;
        }

        #declineRequestModal .modal-footer {
            justify-content: center;
            gap: 10px;
        }

        #decline-request-yes-btn {
            background-color: #dc3545;
            color: #fff;
            font-weight: 700;
        }

        #decline-request-no-btn {
            background-color: #198754;
            color: #fff;
            font-weight: 700;
        }

        #dashboardLightPopup {
            position: fixed;
            left: 50%;
            bottom: 22px;
            transform: translate(-50%, 12px);
            background: rgba(255, 255, 255, 0.96);
            color: #212529;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);
            padding: 10px 14px;
            font-size: 0.92rem;
            font-weight: 600;
            z-index: 1070;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        #dashboardLightPopup.active {
            opacity: 1;
            transform: translate(-50%, 0);
        }

        @media (max-width: 575.98px) {
            #dashboardLightPopup {
                left: 12px;
                right: 12px;
                width: auto;
                max-width: none;
                transform: translate(0, 12px);
                text-align: center;
            }

            #dashboardLightPopup.active {
                transform: translate(0, 0);
            }

            #dashboard-purchases .purchase-listing-card {
                display: flex;
                flex-direction: row;
                align-items: stretch;
                text-align: left !important;
            }

            #dashboard-purchases .purchase-listing-media {
                width: 42%;
                max-width: 42%;
                aspect-ratio: 4 / 5;
                object-fit: cover;
            }

            #dashboard-purchases .purchase-listing-info {
                width: 58%;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 0.6rem 0.6rem 0.6rem 0.55rem;
            }

            #dashboard-purchases .purchase-listing-info .card-title {
                font-size: 1rem;
                margin-bottom: 0.25rem;
            }

            #dashboard-purchases .purchase-listing-info .card-text {
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
            }

            #dashboard-purchases .purchase-listing-info .mark-out-of-stock-btn {
                align-self: flex-start;
                font-size: 0.82rem;
                padding: 0.32rem 0.55rem;
            }
        }

        .owner-out-of-stock-card {
            border: 3px solid #dc3545 !important;
        }

        .pending-request-highlight {
            background-color: rgba(255, 165, 0, 0.22);
            border-radius: 10px;
            padding: 6px 8px;
        }

        #dashboard-purchases .purchase-listing-media-missing {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 180px;
            padding: 0.75rem;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 700;
            text-align: center;
        }

        #dashboard-purchases .purchase-listing-media,
        #dashboard-purchases .purchase-preview-trigger {
            cursor: pointer;
        }

        #dashboard-purchases .proceed-btn-disabled,
        #dashboard-purchases .mark-out-of-stock-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            pointer-events: none;
        }

        .about-purchase {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 14px;
            width: 100%;
        }

        .about-purchase-field {
            min-width: 0;
        }

        .about-purchase-field.service-requirement-field {
            grid-column: 1 / -1;
        }

        .comment-section-bulk-orders {
            align-items: flex-start;
        }

        .profile-name-comment {
            flex: 1;
            min-width: 0;
        }

        .about-purchase-field b {
            display: block;
            margin-bottom: 2px;
        }

        .about-purchase-value {
            display: block;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        @media (max-width: 575.98px) {
            .about-purchase {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px 10px;
            }

            .profile-name-comment .container-fluid {
                margin-left: -80px;
                margin-right: -12px;
                width: calc(100% + 92px);
            }
        }

        .purchase-request-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .messages-themselves {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            width: 100%;
            margin: 10px 0;
            padding-right: 8px;
        }

        .sender-messages-themselves {
            background: rgb(241, 90, 36);
            color: #fff;
            align-self: flex-end;
            margin-left: auto;
            margin-right: 0;
            position: relative;
            text-align: left;
            width: fit-content;
            max-width: min(85vw, 420px);
            padding: 10px 12px;
            border-radius: 14px;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .sender-messages-themselves::after {
            content: '';
            position: absolute;
            right: -8px;
            bottom: 10px;
            width: 0;
            height: 0;
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
            border-left: 10px solid rgb(241, 90, 36);
        }

        .messages-themselves-receiver {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
            margin: 10px 0;
            padding-left: 8px;
        }

        .receiver-messages-themselves {
            background: rgb(0, 0, 255);
            color: #fff;
            align-self: flex-start;
            margin-left: 0;
            margin-right: auto;
            position: relative;
            text-align: left;
            width: fit-content;
            max-width: min(85vw, 420px);
            padding: 10px 12px;
            border-radius: 14px;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .sender-messages-themselves a,
        .receiver-messages-themselves a {
            color: inherit;
            text-decoration: underline;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .receiver-messages-themselves::after {
            content: '';
            position: absolute;
            left: -8px;
            bottom: 10px;
            width: 0;
            height: 0;
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
            border-right: 10px solid rgb(0, 0, 255);
        }

        #inbox-active-panel,
        #inbox-message-thread {
            width: 100%;
        }

        #inbox-message-thread {
            padding-inline: 0;
            padding-bottom: 120px;
        }

        .inbox-day-separator {
            text-align: center;
            margin: 12px 0 10px;
        }

        .inbox-day-separator span {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            background: #e9ecef;
            color: #495057;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .inbox-message-body {
            display: inline;
        }

        .inbox-message-time {
            display: inline-block;
            margin-left: 8px;
            font-size: 0.72rem;
            opacity: 0.9;
            vertical-align: bottom;
            white-space: nowrap;
        }

        .inbox-reply-preview {
            border-left: 3px solid rgb(241, 90, 36);
            background: rgba(255, 255, 255, 0.14);
            border-radius: 10px;
            padding: 6px 8px;
            margin-bottom: 6px;
        }

        .inbox-reply-preview-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 2px;
            color: rgba(255, 255, 255, 0.78);
        }

        .inbox-reply-preview-text {
            display: block;
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.56);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .inbox-compose-reply-box {
            display: none;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            width: 100%;
            box-sizing: border-box;
            text-align: left;
            border-left: 3px solid rgb(241, 90, 36);
            background: rgba(241, 90, 36, 0.12);
            border-radius: 10px;
            padding: 8px 10px;
            margin: 6px 0 8px;
        }

        .inbox-compose-reply-box.active {
            display: flex;
        }

        .inbox-compose-reply-meta {
            min-width: 0;
            flex: 1 1 auto;
        }

        .inbox-compose-reply-box .inbox-reply-preview-label {
            color: rgb(241, 90, 36);
        }

        .inbox-compose-reply-box .inbox-reply-preview-text {
            color: #343a40;
            white-space: normal;
            overflow: hidden;
            text-overflow: unset;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .inbox-compose-reply-cancel {
            border: 0;
            background: transparent;
            color: #6c757d;
            font-size: 1.1rem;
            line-height: 1;
            padding: 0;
            flex: 0 0 auto;
        }

        .inbox-message-bubble-actionable {
            cursor: context-menu;
            -webkit-touch-callout: none;
            user-select: none;
        }

        .inbox-message-action-menu {
            position: fixed;
            min-width: 140px;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.18);
            padding: 6px;
            z-index: 10060;
            display: none;
        }

        .inbox-message-action-menu.active {
            display: block;
        }

        .inbox-message-action-btn {
            width: 100%;
            border: 0;
            background: transparent;
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #dc3545;
            font-weight: 700;
            text-align: left;
        }

        .inbox-message-action-btn:hover,
        .inbox-message-action-btn:focus-visible {
            background: rgba(220, 53, 69, 0.08);
            outline: none;
        }

        .inbox-message-action-icon {
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .inbox-conversation-action-menu {
            position: fixed;
            min-width: 140px;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.18);
            padding: 6px;
            z-index: 10060;
            display: none;
        }

        .inbox-conversation-action-menu.active {
            display: block;
        }

        .inbox-conversation-action-btn {
            width: 100%;
            border: 0;
            background: transparent;
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #dc3545;
            font-weight: 700;
            text-align: left;
        }

        .inbox-conversation-action-btn:hover,
        .inbox-conversation-action-btn:focus-visible {
            background: rgba(220, 53, 69, 0.08);
            outline: none;
        }

        .inbox-conversation-action-icon {
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .sending-date {
            opacity: 0.68;
        }

        #inbox-active-profilepic {
            width: 72px;
            height: 72px;
            object-fit: cover;
            background: #fff;
            border-radius: 50%;
        }

        #inbox-active-profilepic.inbox-profilepic--system {
            object-fit: contain;
        }

        #inbox-active-business-name {
            font-size: 1.35rem;
            font-weight: 700;
            margin-top: 8px;
        }

        .inbox-active-profile-link {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }

        .inbox-chat-only-hidden {
            display: none !important;
        }

        .inbox-list-only-hidden {
            display: none !important;
        }

        .inbox-chat-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .inbox-conversation-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            overflow: hidden;
            text-align: left;
            padding: 8px 12px 8px 0;
        }

        .inbox-conversation-meta {
            min-width: 0;
            flex: 0 1 74%;
            max-width: 74%;
        }

        #inbox-conversation-list {
            text-align: left !important;
            overflow-x: hidden;
        }

        .inbox-empty-state {
            width: 100%;
            padding: 32px 18px;
            border-radius: 18px;
            background: #f7f7f8;
            color: #5f6773;
            text-align: center;
        }

        .inbox-empty-state strong {
            display: block;
            margin-bottom: 6px;
            color: #1f2937;
            font-size: 1rem;
        }

        .inbox-empty-state p {
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .inbox-list-dp {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
            flex: 0 0 auto;
        }

        .inbox-list-dp.inbox-list-dp--system {
            object-fit: contain;
        }

        .inbox-conversation-name {
            margin: 0;
            font-size: 1rem;
            line-height: 1.2;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .inbox-preview-text {
            margin: 2px 0 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: none;
            font-size: 0.92rem;
            color: #4e5561;
            flex: 1 1 auto;
            min-width: 0;
        }

        .inbox-preview-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 2px;
            min-width: 0;
        }

        .inbox-conversation-time {
            margin: 0;
            flex: 0 0 auto;
            white-space: nowrap;
            font-size: 0.78rem;
            color: #6c757d;
            align-self: center;
        }

        #navbarone .main-navbar-chat-mode {
            display: none;
        }

        #navbarone.chat-mode > *:not(#main-navbar-chat-mode) {
            display: none !important;
        }

        #navbarone.chat-mode .main-navbar-chat-mode {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            width: 100%;
            min-height: 68px;
            padding: 7px 0;
        }

        #navbarone.chat-mode {
            height: 60px !important;
            min-height: 60px !important;
        }

        .main-navbar-chat-back {
            border: 0;
            background: transparent;
            color: #000;
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
            padding: 0 8px 0 6px;
            margin: 0;
            cursor: pointer;
            flex: 0 0 auto;
        }

        .main-navbar-chat-profile {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: rgb(0, 0, 255);
            min-width: 0;
        }

        .main-navbar-chat-profilepic {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
            flex: 0 0 auto;
        }

        .main-navbar-chat-profilepic.main-navbar-chat-profilepic--system {
            object-fit: contain;
        }

        .main-navbar-chat-business-name {
            font-weight: 700;
            font-size: 1.2rem;
            line-height: 1.2;
            color: #000;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: min(66vw, 520px);
        }

        #dashboard-inbox.inbox-chat-page {
            display: flex !important;
            flex-direction: column;
            height: calc(100svh - 68px);
        }

        #dashboard-inbox.inbox-chat-page #inbox-active-panel {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
        }

        #dashboard-inbox.inbox-chat-page #inbox-message-thread {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding-bottom: 86px;
        }

        #dashboard-inbox.inbox-chat-page .message-area-div {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 20;
            margin: 0 !important;
            padding: 2px 8px calc(2px + env(safe-area-inset-bottom, 0px)) !important;
            margin-top: auto !important;
            background: #fff;
            box-shadow: none;
            align-items: flex-end;
            gap: 0;
            top: auto;
        }

        #dashboard-inbox.inbox-chat-page .message-area-div .inbox-message-input {
            margin: 0;
            min-height: 44px;
            align-self: flex-end
        }

        #dashboard-inbox.inbox-chat-page .message-area-div .inbox-send-btn {
            margin: 0;
            flex: 0 0 auto;
            align-self: flex-end;
        }

        #dashboard-inbox.inbox-chat-page .inbox-chat-header {
            display: flex;
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 10px 10px;
        }

        #dashboard-inbox.inbox-chat-page .inbox-active-profile-link {
            flex-direction: row;
            align-items: center;
            gap: 10px;
        }

        #dashboard-inbox.inbox-chat-page #inbox-active-profilepic {
            width: 36px;
            height: 36px;
        }

        #dashboard-inbox.inbox-chat-page #inbox-active-business-name {
            margin-top: 0;
            font-size: 1rem;
        }

        body.vendor-dashboard-page.inbox-chat-active > footer {
            display: none !important;
        }



        @media (max-width: 991.98px) {
            #navbarone {
                position: fixed;
                justify-content: space-between;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                gap: 0;
            }

            #navbarone .vendor-dashboard-brand-wrap {
                margin: 0;
                padding: 0 0 0 2px !important;
                z-index: 2;
            }

            #navbarone .vendor-dashboard-brand-wrap .navbar-brand {
                margin: 0;
                padding: 0;
            }

            #navbarone .vendor-dashboard-brand-wrap .brand-logos {
                margin: 0;
                padding-left: 0 !important;
            }

            #navbarone .vendor-dashboard-brand-wrap .logo {
                width: clamp(88px, 12vw, 108px);
            }

            #navbarone .vendor-dashboard-title-wrap {
                position: absolute;
                left: 94px;
                right: 48px;
                top: 50%;
                transform: translateY(-50%);
                width: auto;
                max-width: none;
                margin: 0 !important;
                padding: 0 !important;
                pointer-events: none;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            #navbarone .vendor-dashboard-title {
                font-size: clamp(1.18rem, 4.2vw, 1.62rem);
                white-space: nowrap;
                overflow: visible;
                text-overflow: unset;
            }

            #navbarone .navbar-toggler {
                margin: 0;
                margin-right: 2px;
                width: clamp(34px, 6.4vw, 40px);
                height: clamp(34px, 6.4vw, 40px);
                z-index: 2;
            }

            #navbarone .dashboard-mobile-auth-trigger {
                width: clamp(34px, 6.4vw, 40px);
                height: clamp(34px, 6.4vw, 40px);
            }

            #navbarone .dashboard-mobile-auth-icon {
                width: clamp(28px, 5.4vw, 34px);
                height: clamp(28px, 5.4vw, 34px);
             }

            @media (max-width: 767.98px) {
                body.vendor-dashboard-page > header {
                    padding-top: 44px;
                }

                #dashboard-secondary-nav {
                    padding-top: 6px;
                    padding-bottom: 4px;
                    border: 0 !important;
                    border-bottom: 0 !important;
                    box-shadow: none !important;
                }

                #navbarone {
                    height: 45px;
                    min-height: 45px;
                    align-items: center;
                    justify-content: space-between;
                    flex-wrap: nowrap;
                    padding-left: 0 !important;
                    padding-right: 4px !important;
                    line-height: 1;
                    border: 0 !important;
                    box-shadow: none !important;
                }

                #navbarone .vendor-dashboard-brand-wrap {
                    padding-left: 0 !important;
                    margin-left: -12px;
                    flex: 0 0 auto;
                    display: flex;
                    align-items: center;
                    height: 100%;
                }

                #navbarone .vendor-dashboard-brand-wrap .navbar-brand,
                #navbarone .vendor-dashboard-brand-wrap .brand-logos {
                    display: flex;
                    align-items: center;
                    line-height: 1;
                }

                #navbarone .vendor-dashboard-brand-wrap .logo {
                    width: clamp(88px, 26vw, 112px);
                    max-width: none;
                    display: block;
                }

                #navbarone .vendor-dashboard-title-wrap {
                    position: static;
                    left: auto;
                    right: auto;
                    top: auto;
                    transform: none;
                    flex: 1 1 auto;
                    width: auto;
                    max-width: none;
                    min-width: 0;
                    padding: 0 !important;
                    align-self: center;
                    margin-left: -14px !important;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100%;
                }

                #navbarone .vendor-dashboard-title {
                    /* font-size: 1.45rem; */
                    font-size: clamp(1.1rem, 6.1vw, 1.7rem);
                    line-height: 1.2;
                    height: auto;
                    margin: 0;
                    max-width: 100%;
                    overflow: hidden;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                }

                #navbarone .dashboard-mobile-auth-dropdown {
                    flex: 0 0 auto;
                    margin-left: auto;
                    margin-right: 1px;
                    display: flex;
                    align-items: center;
                    align-self: center;
                    height: 100%;
                }

                #navbarone .dashboard-mobile-auth-trigger {
                    width: clamp(36px, 10vw, 45px);
                    height: clamp(36px, 10vw, 45px);
                    padding: 0;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }

                #navbarone .dashboard-mobile-auth-icon {
                    width: clamp(31px, 8vw, 38px);
                    height: clamp(31px, 8vw, 38px);
                    object-fit: contain;
                    display: block;
                }
            }

            #inbox-conversation-list {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            .inbox-conversation-item {
                padding: 10px 12px 10px 0;
            }

            .inbox-conversation-meta {
                flex: 1 1 auto;
                max-width: none;
            }

            .vendor-dashboard-page .footer-feedback br {
                display: none;
            }

            .vendor-dashboard-page .footer-feedback small {
                display: block;
                margin-top: 0;
            }

            #dashboard-listings .add-listing-card-wrap {
                max-width: 160px;
            }

            #dashboard-listings .add-listing-card {
                max-width: 144px;
            }

            #dashboard-listings .add-listing-card .card-img-top {
                max-height: 48px;
                max-width: 68%;
            }

            #dashboard-listings .add-listing-card .add-listing-card-label {
                font-size: 0.88rem;
            }

            #dashboard-listings .showroom-container {
                --bs-gutter-x: 1px;
                --bs-gutter-y: 1px;
            }

            #dashboard-listings .dashboard-showroom-grid {
                --bs-gutter-x: 1px;
                --bs-gutter-y: 1px;
                margin-top: 0;
            }

            #dashboard-listings .showroom-img .card-img-showroom {
                width: 100%;
                height: auto;
                aspect-ratio: 4 / 5;
                object-fit: cover;
            }

            #dashboard-listings .showroom-img .video-wrapper {
                height: auto;
                padding-top: 125%;
            }

            #dashboard-listings .dots-icon {
                width: 24px;
                height: 40px;
                margin-top: 30px;
            }

            #dashboard-listings .card-options .dropdown-content {
                top: 64px !important;
            }

        }


    .mobile-auth-dropdown {
        position: relative;
    }

    #navbarone .dashboard-mobile-auth-dropdown .mobile-auth-menu {
        left: auto;
        right: 0 !important;
        top: 100%;
        /* background: rgb(0,0,255); */
        min-width: 190px;
        padding-left: 12px;
        margin-top: -1px;
        transform: none !important;
    }

    #navbarone .dashboard-mobile-auth-dropdown .mobile-auth-menu::before {
        content: "";
        position: absolute;
        top: -6px;
        right: 10px;
        width: 14px;
        height: 14px;
        background: #fff;
        /* background: rgb(0,0,255); */
        transform: rotate(45deg);
        border-radius: 2px;
        box-shadow: -2px -2px 8px rgba(17, 24, 39, 0.04);
    }
    </style>
</head>

<?php 
session_start();

 if (!isset($_SESSION['emailormobilenumber'])) {
    header('location: /?error=Not+Signed+In!');
    exit;
 }

 include "connection/dbconn.php";
 include "partials/helpers.php";
 include "partials/admin_helpers.php";

 jomu_ensure_admin_schema($conn);

 $dashboardUser = null;
 $userStmt = $conn->prepare("SELECT id, businessname, profilepic, account_status, inactive_until, status_reason FROM users WHERE emailormobilenumber = ? LIMIT 1");
 $userStmt->bind_param("s", $_SESSION['emailormobilenumber']);
 $userStmt->execute();
 $dashboardUser = $userStmt->get_result()->fetch_assoc();
 $userStmt->close();

 if (!$dashboardUser) {
    header('location: /?error=User+Not+Found');
    exit;
 }

 if (strtolower((string) ($dashboardUser['account_status'] ?? 'active')) === 'terminated') {
    session_destroy();
    $terminatedMsg = 'This business account was terminated.';
    header('location: /signin.html?error=' . rawurlencode($terminatedMsg));
    exit;
 }
 $accountSuspended = false;
 $suspensionUntilLabel = '';
 $suspensionReasonLabel = '';
 if (strtolower((string) ($dashboardUser['account_status'] ?? 'active')) === 'inactive') {
    $inactiveUntil = trim((string) ($dashboardUser['inactive_until'] ?? ''));
    if ($inactiveUntil !== '' && strtotime($inactiveUntil) !== false && strtotime($inactiveUntil) <= time()) {
        $activateStmt = $conn->prepare("UPDATE users SET account_status = 'active', inactive_until = NULL, status_reason = NULL WHERE id = ?");
        if ($activateStmt) {
            $activateStmt->bind_param('i', $dashboardUser['id']);
            $activateStmt->execute();
            $activateStmt->close();
        }
        $dashboardUser['account_status'] = 'active';
    } else {
        $accountSuspended = true;
        $inactiveTs = $inactiveUntil !== '' ? strtotime($inactiveUntil) : false;
        $suspensionUntilLabel = $inactiveTs !== false ? date('j M Y, g:i a', $inactiveTs) : $inactiveUntil;
        $suspensionReasonLabel = trim((string) ($dashboardUser['status_reason'] ?? ''));
        if ($suspensionReasonLabel === '') {
            $suspensionReasonLabel = 'This account was suspended for going against JoMu Policy.';
        }
    }
 }

 $viewsColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'views'");
 if (!$viewsColumnCheck || $viewsColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN views INT NOT NULL DEFAULT 0");
 }

$outOfStockColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'out_of_stock'");
if (!$outOfStockColumnCheck || $outOfStockColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN out_of_stock TINYINT(1) NOT NULL DEFAULT 0");
}

$businessContactColumnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'business_contact'");
if (!$businessContactColumnCheck || $businessContactColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN business_contact VARCHAR(60) NULL");
}

$purchaseStorageSql = "
    CREATE TABLE IF NOT EXISTS purchase_requests (
        request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        listing_id INT UNSIGNED NOT NULL,
        seller_user_id INT UNSIGNED NOT NULL,
        buyer_user_id INT UNSIGNED NOT NULL,
        listing_type VARCHAR(20) NOT NULL DEFAULT 'product',
        amount VARCHAR(255) NOT NULL,
        payment_mode VARCHAR(255) NOT NULL,
        delivery_method VARCHAR(255) NULL,
        location VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_seller_created (seller_user_id, created_at),
        INDEX idx_listing_created (listing_id, created_at),
        INDEX idx_buyer_created (buyer_user_id, created_at)
    )
";
$conn->query($purchaseStorageSql);

$businessMessagesTableSql = "
    CREATE TABLE IF NOT EXISTS business_messages (
        message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sender_user_id INT UNSIGNED NOT NULL,
        receiver_user_id INT UNSIGNED NOT NULL,
        message_type VARCHAR(20) NOT NULL DEFAULT 'text',
        message_text TEXT NULL,
        media_path VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sender_created (sender_user_id, created_at),
        INDEX idx_receiver_created (receiver_user_id, created_at)
    )
";
$conn->query($businessMessagesTableSql);

function ensureBusinessMessageReadsTable(mysqli $conn): bool {
    $sql = "
    CREATE TABLE IF NOT EXISTS business_message_reads (
        user_id INT UNSIGNED NOT NULL,
        partner_user_id INT UNSIGNED NOT NULL,
        last_read_message_id INT UNSIGNED NOT NULL DEFAULT 0,
        last_read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, partner_user_id),
        INDEX idx_partner_user (partner_user_id, user_id)
    )
";

    return (bool) $conn->query($sql);
}

ensureBusinessMessageReadsTable($conn);

function ensureBusinessMessageHiddenTable(mysqli $conn): bool {
    $sql = "
    CREATE TABLE IF NOT EXISTS business_message_hidden_for_user (
        message_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        hidden_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (message_id, user_id),
        INDEX idx_user_hidden (user_id, hidden_at)
    )
";

    return (bool) $conn->query($sql);
}

ensureBusinessMessageHiddenTable($conn);

function ensureBusinessMessageReplyColumns(mysqli $conn): void {
    $replyColumnCheck = $conn->query("SHOW COLUMNS FROM business_messages LIKE 'reply_to_message_id'");
    if (!$replyColumnCheck || $replyColumnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE business_messages ADD COLUMN reply_to_message_id INT UNSIGNED NULL AFTER media_path");
    }
}

ensureBusinessMessageReplyColumns($conn);

function getDashboardBadgeCounts($conn, $userId) {
    $counts = [
        'purchase_count' => 0,
        'inbox_count' => 0
    ];

    $pendingStatus = 'pending';
    $purchaseCountStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM purchase_requests pr
         INNER JOIN listings l ON l.listing_id = pr.listing_id
         WHERE pr.seller_user_id = ? AND LOWER(COALESCE(pr.status, 'pending')) = ?"
    );
    if ($purchaseCountStmt) {
        $purchaseCountStmt->bind_param("is", $userId, $pendingStatus);
        $purchaseCountStmt->execute();
        $purchaseCountRes = $purchaseCountStmt->get_result()->fetch_assoc();
        $counts['purchase_count'] = (int) ($purchaseCountRes['total'] ?? 0);
        $purchaseCountStmt->close();
    }

    $inboxCountStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM (
            SELECT bm.sender_user_id
            FROM business_messages bm
            LEFT JOIN business_message_hidden_for_user bmh
                ON bmh.message_id = bm.message_id
               AND bmh.user_id = ?
            LEFT JOIN business_message_reads bmr
                ON bmr.user_id = ?
               AND bmr.partner_user_id = bm.sender_user_id
            WHERE bm.receiver_user_id = ?
              AND bmh.message_id IS NULL
              AND bm.message_id > COALESCE(bmr.last_read_message_id, 0)
            GROUP BY bm.sender_user_id
         ) unread_business_conversations"
    );
    if ($inboxCountStmt) {
        $inboxCountStmt->bind_param("iii", $userId, $userId, $userId);
        $inboxCountStmt->execute();
        $inboxCountRes = $inboxCountStmt->get_result()->fetch_assoc();
        $counts['inbox_count'] = (int) ($inboxCountRes['total'] ?? 0);
        $inboxCountStmt->close();
    }

    return $counts;
}

function getDashboardInboxUnreadCounts(mysqli $conn, int $userId): array {
    $counts = [];

    $stmt = $conn->prepare(
        "SELECT
            bm.sender_user_id AS partner_id,
            COUNT(*) AS unread_count
         FROM business_messages bm
         LEFT JOIN business_message_hidden_for_user bmh
            ON bmh.message_id = bm.message_id
           AND bmh.user_id = ?
         LEFT JOIN business_message_reads bmr
            ON bmr.user_id = ?
           AND bmr.partner_user_id = bm.sender_user_id
         WHERE bm.receiver_user_id = ?
           AND bmh.message_id IS NULL
           AND bm.message_id > COALESCE(bmr.last_read_message_id, 0)
         GROUP BY bm.sender_user_id"
    );

    if (!$stmt) {
        return $counts;
    }

    $stmt->bind_param("iii", $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $partnerId = (int) ($row['partner_id'] ?? 0);
        if ($partnerId <= 0) {
            continue;
        }

        $counts[$partnerId] = (int) ($row['unread_count'] ?? 0);
    }
    $stmt->close();

    return $counts;
}

$dashboardBadgeCounts = getDashboardBadgeCounts($conn, (int) $dashboardUser['id']);
$purchaseBadgeCount = (int) ($dashboardBadgeCounts['purchase_count'] ?? 0);
$inboxBadgeCount = (int) ($dashboardBadgeCounts['inbox_count'] ?? 0);
$inboxUnreadCounts = getDashboardInboxUnreadCounts($conn, (int) $dashboardUser['id']);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['badge_counts'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'purchase_count' => $purchaseBadgeCount,
        'inbox_count' => $inboxBadgeCount,
        'inbox_unread_counts' => $inboxUnreadCounts
    ]);
    exit;
}

 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_out_of_stock_listing_id'])) {
    jomu_require_csrf();
    $markOutListingId = (int) ($_POST['mark_out_of_stock_listing_id'] ?? 0);
    $isAjaxRequest = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $markOutUpdated = false;
    $newOutOfStockState = 0;

    if ($markOutListingId > 0) {
        $listingStateStmt = $conn->prepare("SELECT COALESCE(out_of_stock, 0) AS out_of_stock FROM listings WHERE listing_id = ? AND user_id = ? LIMIT 1");
        if ($listingStateStmt) {
            $listingStateStmt->bind_param("ii", $markOutListingId, $dashboardUser['id']);
            $listingStateStmt->execute();
            $listingStateRow = $listingStateStmt->get_result()->fetch_assoc();
            $listingStateStmt->close();

            if ($listingStateRow) {
                $newOutOfStockState = !empty($listingStateRow['out_of_stock']) ? 0 : 1;
                $markOutStmt = $conn->prepare("UPDATE listings SET out_of_stock = ? WHERE listing_id = ? AND user_id = ?");
                if ($markOutStmt) {
                    $markOutStmt->bind_param("iii", $newOutOfStockState, $markOutListingId, $dashboardUser['id']);
                    $markOutStmt->execute();
                    $markOutUpdated = $markOutStmt->affected_rows >= 0;
                    $markOutStmt->close();
                }
            }
        }
    }

    if ($isAjaxRequest) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'updated' => $markOutUpdated,
            'listing_id' => $markOutListingId,
            'out_of_stock' => $newOutOfStockState === 1,
            'button_label' => $newOutOfStockState === 1 ? 'Out of Stock.' : 'Mark as out of stock'
        ]);
        exit;
    }

    header('Location: businessvendordashboard.php');
    exit;
 }

 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decline_purchase_request_id'])) {
    jomu_require_csrf();
    $declineRequestId = (int) ($_POST['decline_purchase_request_id'] ?? 0);
    $isAjaxRequest = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $declineSuccess = false;

    if ($declineRequestId > 0) {
        $declineStmt = $conn->prepare("DELETE FROM purchase_requests WHERE request_id = ? AND seller_user_id = ? LIMIT 1");
        if ($declineStmt) {
            $declineStmt->bind_param("ii", $declineRequestId, $dashboardUser['id']);
            $declineStmt->execute();
            $declineSuccess = $declineStmt->affected_rows > 0;
            $declineStmt->close();
        }
    }

    if ($isAjaxRequest) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $declineSuccess,
            'request_id' => $declineRequestId
        ]);
        exit;
    }

    header('Location: businessvendordashboard.php');
    exit;
 }

 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_purchase_request_proceeded_id'])) {
    jomu_require_csrf();
    $proceededRequestId = (int) ($_POST['mark_purchase_request_proceeded_id'] ?? 0);
    $isAjaxRequest = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $proceededUpdated = false;

    if ($proceededRequestId > 0) {
        $proceedStatus = 'proceeded';
        $proceedStmt = $conn->prepare("UPDATE purchase_requests SET status = ? WHERE request_id = ? AND seller_user_id = ?");
        if ($proceedStmt) {
            $proceedStmt->bind_param("sii", $proceedStatus, $proceededRequestId, $dashboardUser['id']);
            $proceedStmt->execute();
            $proceededUpdated = $proceedStmt->affected_rows > 0;
            $proceedStmt->close();
        }
    }

    if ($isAjaxRequest) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'updated' => $proceededUpdated,
            'request_id' => $proceededRequestId
        ]);
        exit;
    }

    header('Location: businessvendordashboard.php');
    exit;
 }

 $dashboardListings = [];
 $listingsStmt = $conn->prepare(
    "SELECT * FROM listings WHERE user_id = ? ORDER BY COALESCE(views, 0) DESC, listing_id DESC"
 );
 $listingsStmt->bind_param("i", $dashboardUser['id']);
 $listingsStmt->execute();
 $listingsRes = $listingsStmt->get_result();
 while ($row = $listingsRes->fetch_assoc()) {
    $dashboardListings[] = $row;
 }
 $listingsStmt->close();

 $dashboardProductCount = 0;
 $dashboardServiceCount = 0;
 foreach ($dashboardListings as $dashboardListing) {
    $dashboardListingType = strtolower((string) ($dashboardListing['listing_type'] ?? ''));
    if ($dashboardListingType !== 'product' && $dashboardListingType !== 'service') {
        $dashboardCategoryText = strtolower((string) ($dashboardListing['category'] ?? ''));
        $dashboardListingType = strpos($dashboardCategoryText, 'service') !== false ? 'service' : 'product';
    }
    if ($dashboardListingType === 'service') {
        $dashboardServiceCount++;
    } else {
        $dashboardProductCount++;
    }
 }
$manageRequestsLabel = $dashboardServiceCount > $dashboardProductCount ? 'Manage Schedule' : 'Manage Purchases';
 $purchasesSectionTitle = $dashboardServiceCount > $dashboardProductCount ? 'Schedule' : 'Purchases';
 $purchasesListingsHeading = $dashboardServiceCount > $dashboardProductCount ? 'Services Scheduled' : 'Listings Purchased';

$purchaseRequestsByListing = [];

 $purchaseStmt = $conn->prepare(
    "SELECT
        pr.*,
        l.media AS listing_media,
        l.stockname AS listing_stockname,
        l.description AS listing_description,
        l.price AS listing_price,
        l.price_from AS listing_price_from,
        l.price_to AS listing_price_to,
        l.listing_type AS listing_listing_type,
        l.category AS listing_category,
        l.out_of_stock AS out_of_stock,
        u.businessname AS buyer_businessname,
        u.profilepic AS buyer_profilepic,
        u.business_contact AS buyer_business_contact,
        UNIX_TIMESTAMP(pr.created_at) AS request_created_ts,
        UNIX_TIMESTAMP(NOW()) AS current_db_ts
     FROM purchase_requests pr
     INNER JOIN listings l ON l.listing_id = pr.listing_id
     LEFT JOIN users u ON u.id = pr.buyer_user_id
     WHERE pr.seller_user_id = ?
     ORDER BY pr.created_at DESC, pr.request_id DESC"
 );
 $purchaseStmt->bind_param("i", $dashboardUser['id']);
 $purchaseStmt->execute();
 $purchaseRes = $purchaseStmt->get_result();
while ($purchaseRow = $purchaseRes->fetch_assoc()) {
    $listingKey = (int) ($purchaseRow['listing_id'] ?? 0);
    if ($listingKey <= 0) {
        continue;
    }
    if (!isset($purchaseRequestsByListing[$listingKey])) {
        $purchaseRequestsByListing[$listingKey] = [];
    }
    $purchaseRequestsByListing[$listingKey][] = $purchaseRow;
}
 $purchaseStmt->close();

 function formatRequestTimeAgo($dateTimeValue, $currentDbTs = null, $createdTs = null) {
    $currentDbTs = (int) $currentDbTs;
    $createdTs = (int) $createdTs;

    if ($currentDbTs > 0 && $createdTs > 0) {
        $seconds = $currentDbTs - $createdTs;
    } else {
        $ts = strtotime((string) $dateTimeValue);
        if (!$ts) {
            return '';
        }
        $seconds = time() - $ts;
    }

    if ($seconds <= 0 || $seconds < 60) {
        return 'Just now';
    }

    if ($seconds < 3600) {
        $minutes = (int) floor($seconds / 60);
        return $minutes . ' ' . ($minutes === 1 ? 'min' : 'min') . ' ago';
    }

    if ($seconds < 86400) {
        $hours = (int) floor($seconds / 3600);
        return $hours . ' ' . ($hours === 1 ? 'hr' : 'hrs') . ' ago';
    }

    if ($seconds < 2592000) {
        $days = (int) floor($seconds / 86400);
        return $days . ' ' . ($days === 1 ? 'day' : 'days') . ' ago';
    }

    if ($seconds < 31536000) {
        $months = (int) floor($seconds / 2592000);
        return $months . ' ' . ($months === 1 ? 'month' : 'months') . ' ago';
    }

    $years = (int) floor($seconds / 31536000);
    return $years . ' ' . ($years === 1 ? 'year' : 'years') . ' ago';
 }

 function formatInboxConversationTimeAgo($dateTimeValue) {
    $ts = strtotime((string) $dateTimeValue);
    if (!$ts) {
        return '';
    }

    $seconds = time() - $ts;
    if ($seconds <= 0 || $seconds < 60) {
        return 'Just now';
    }
    if ($seconds < 3600) {
        $minutes = (int) floor($seconds / 60);
        return $minutes . ' min ago';
    }
    if ($seconds < 86400) {
        $hours = (int) floor($seconds / 3600);
        return $hours . ' hr ago';
    }
    if ($seconds < 2592000) {
        $days = (int) floor($seconds / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }
    if ($seconds < 31536000) {
        $months = (int) floor($seconds / 2592000);
        return $months . ' month' . ($months === 1 ? '' : 's') . ' ago';
    }

    $years = (int) floor($seconds / 31536000);
    return $years . ' year' . ($years === 1 ? '' : 's') . ' ago';
 }

 function formatInboxTimestampLabel($dateTimeValue) {
    $ts = strtotime((string) $dateTimeValue);
    if (!$ts) {
        return '';
    }

    $oneYearAgoTs = strtotime('-1 year');
    $format = ($oneYearAgoTs !== false && $ts <= $oneYearAgoTs) ? 'j M. Y. g:ia' : 'j M. g:ia';
    return date($format, $ts);
 }

 function formatInboxMediaPath($path) {
    return jomu_resolve_public_profile_image_path((string) $path);
 }

 $inboxConversations = [];
 $jomuSystemUserId = jomu_system_user_id($conn);
 $initialDashboardSection = isset($_GET['section']) ? strtolower(trim((string) $_GET['section'])) : 'listings';
 if (!in_array($initialDashboardSection, ['listings', 'purchases', 'inbox'], true)) {
    $initialDashboardSection = 'listings';
 }
 $requestedInboxPartnerId = isset($_GET['partner_id']) ? (int) $_GET['partner_id'] : 0;
 if ($requestedInboxPartnerId <= 0 || $requestedInboxPartnerId === (int) $dashboardUser['id']) {
    $requestedInboxPartnerId = 0;
 }
 $conversationPartnerIds = [];

 $messagePartnersStmt = $conn->prepare(
    "SELECT
        visible_messages.partner_id,
        MAX(visible_messages.created_at) AS latest_created_at
     FROM (
        SELECT
            CASE
                WHEN bm.sender_user_id = ? THEN bm.receiver_user_id
                ELSE bm.sender_user_id
            END AS partner_id,
            bm.created_at
        FROM business_messages bm
        LEFT JOIN business_message_hidden_for_user bmh
            ON bmh.message_id = bm.message_id
           AND bmh.user_id = ?
        WHERE bmh.message_id IS NULL
          AND (bm.sender_user_id = ? OR bm.receiver_user_id = ?)
     ) visible_messages
     GROUP BY partner_id"
 );
 if ($messagePartnersStmt) {
    $messagePartnersStmt->bind_param("iiii", $dashboardUser['id'], $dashboardUser['id'], $dashboardUser['id'], $dashboardUser['id']);
    $messagePartnersStmt->execute();
    $messagePartnersRes = $messagePartnersStmt->get_result();
    while ($partnerRow = $messagePartnersRes->fetch_assoc()) {
        $partnerId = (int) ($partnerRow['partner_id'] ?? 0);
        if ($partnerId <= 0 || $partnerId === (int) $dashboardUser['id']) {
            continue;
        }
        $conversationPartnerIds[$partnerId] = true;
    }
    $messagePartnersStmt->close();
 }

 $partnerIds = array_keys($conversationPartnerIds);
 if (!empty($partnerIds)) {
    $partnerUserStmt = $conn->prepare("SELECT id, businessname, profilepic FROM users WHERE id = ? LIMIT 1");
    $latestMessageStmt = $conn->prepare(
        "SELECT bm.message_id, bm.sender_user_id, bm.receiver_user_id, bm.message_type, bm.message_text, bm.media_path, bm.reply_to_message_id, bm.created_at
         FROM business_messages bm
         LEFT JOIN business_message_hidden_for_user bmh
            ON bmh.message_id = bm.message_id
           AND bmh.user_id = ?
         WHERE bmh.message_id IS NULL
           AND ((bm.sender_user_id = ? AND bm.receiver_user_id = ?) OR (bm.sender_user_id = ? AND bm.receiver_user_id = ?))
         ORDER BY bm.created_at DESC, bm.message_id DESC
         LIMIT 1"
    );
    $messagesStmt = $conn->prepare(
        "SELECT
            bm.message_id,
            bm.sender_user_id,
            bm.receiver_user_id,
            bm.message_type,
            bm.message_text,
            bm.media_path,
            bm.reply_to_message_id,
            bm.created_at,
            rm.message_text AS reply_message_text,
            rm.sender_user_id AS reply_sender_user_id
         FROM business_messages bm
         LEFT JOIN business_message_hidden_for_user bmh
            ON bmh.message_id = bm.message_id
           AND bmh.user_id = ?
         LEFT JOIN business_messages rm
            ON rm.message_id = bm.reply_to_message_id
         WHERE bmh.message_id IS NULL
           AND ((bm.sender_user_id = ? AND bm.receiver_user_id = ?) OR (bm.sender_user_id = ? AND bm.receiver_user_id = ?))
         ORDER BY bm.created_at ASC, bm.message_id ASC"
    );

    foreach ($partnerIds as $partnerId) {
        $partnerUserStmt->bind_param("i", $partnerId);
        $partnerUserStmt->execute();
        $partnerUser = $partnerUserStmt->get_result()->fetch_assoc();
        if (!$partnerUser) {
            continue;
        }

        $messages = [];
        $latestMessage = null;

        $latestMessageStmt->bind_param("iiiii", $dashboardUser['id'], $dashboardUser['id'], $partnerId, $partnerId, $dashboardUser['id']);
        $latestMessageStmt->execute();
        $latestMessage = $latestMessageStmt->get_result()->fetch_assoc();

        $messagesStmt->bind_param("iiiii", $dashboardUser['id'], $dashboardUser['id'], $partnerId, $partnerId, $dashboardUser['id']);
        $messagesStmt->execute();
        $messagesRes = $messagesStmt->get_result();
        while ($messageRow = $messagesRes->fetch_assoc()) {
            $messages[] = [
                'message_id' => (int) ($messageRow['message_id'] ?? 0),
                'direction' => (int) ($messageRow['sender_user_id'] ?? 0) === (int) $dashboardUser['id'] ? 'outgoing' : 'incoming',
                'type' => (string) ($messageRow['message_type'] ?? 'text'),
                'text' => (string) ($messageRow['message_text'] ?? ''),
                'media_path' => formatInboxMediaPath((string) ($messageRow['media_path'] ?? '')),
                'reply_to_message_id' => (int) ($messageRow['reply_to_message_id'] ?? 0),
                'reply_text' => (string) ($messageRow['reply_message_text'] ?? ''),
                'reply_direction' => (int) ($messageRow['reply_sender_user_id'] ?? 0) === (int) $dashboardUser['id'] ? 'outgoing' : 'incoming',
                'created_at' => (string) ($messageRow['created_at'] ?? ''),
                'timestamp_label' => formatInboxTimestampLabel((string) ($messageRow['created_at'] ?? ''))
            ];
        }

        $latestCreatedAt = (string) ($latestMessage['created_at'] ?? '');
        $sortTimestamp = $latestCreatedAt !== '' ? (strtotime($latestCreatedAt) ?: 0) : 0;
        $previewText = 'No messages yet.';
        if ($latestMessage) {
            $previewText = trim((string) ($latestMessage['message_text'] ?? ''));
            if ($previewText === '') {
                $previewText = 'Message';
            }
        }

        $inboxConversations[] = [
            'partner_id' => $partnerId,
            'partner_name' => $partnerId === $jomuSystemUserId ? 'JoMu' : (trim((string) ($partnerUser['businessname'] ?? 'Business')) ?: 'Business'),
            'partner_profilepic' => $partnerId === $jomuSystemUserId ? '/assets/images/JoMu logo redesigned.png' : formatInboxMediaPath((string) ($partnerUser['profilepic'] ?? '')),
            'is_system_conversation' => $partnerId === $jomuSystemUserId,
            'preview_text' => $previewText,
            'latest_created_at' => $latestCreatedAt,
            'latest_time_label' => $latestCreatedAt !== '' ? formatInboxConversationTimeAgo($latestCreatedAt) : '',
            'sort_timestamp' => $sortTimestamp,
            'messages' => $messages
        ];
    }

    $partnerUserStmt->close();
    $latestMessageStmt->close();
    $messagesStmt->close();
 }

 usort($inboxConversations, static function ($left, $right) {
    return (int) ($right['sort_timestamp'] ?? 0) <=> (int) ($left['sort_timestamp'] ?? 0);
 });

 if ($requestedInboxPartnerId > 0) {
    $existingConversation = false;
    foreach ($inboxConversations as $conversation) {
        if ((int) ($conversation['partner_id'] ?? 0) === $requestedInboxPartnerId) {
            $existingConversation = true;
            break;
        }
    }

    if (!$existingConversation) {
        $requestedPartnerStmt = $conn->prepare("SELECT id, businessname, profilepic FROM users WHERE id = ? LIMIT 1");
        if ($requestedPartnerStmt) {
            $requestedPartnerStmt->bind_param("i", $requestedInboxPartnerId);
            $requestedPartnerStmt->execute();
            $requestedPartner = $requestedPartnerStmt->get_result()->fetch_assoc();
            $requestedPartnerStmt->close();

            if ($requestedPartner) {
                $inboxConversations[] = [
                    'partner_id' => (int) ($requestedPartner['id'] ?? 0),
                    'partner_name' => (int) ($requestedPartner['id'] ?? 0) === $jomuSystemUserId ? 'JoMu' : (trim((string) ($requestedPartner['businessname'] ?? 'Business')) ?: 'Business'),
                    'partner_profilepic' => (int) ($requestedPartner['id'] ?? 0) === $jomuSystemUserId ? '/assets/images/JoMu logo redesigned.png' : formatInboxMediaPath((string) ($requestedPartner['profilepic'] ?? '')),
                    'is_system_conversation' => (int) ($requestedPartner['id'] ?? 0) === $jomuSystemUserId,
                    'preview_text' => 'No messages yet.',
                    'latest_created_at' => '',
                    'latest_time_label' => '',
                    'sort_timestamp' => time(),
                    'messages' => []
                ];
            }
        }
    }
 }

 $dashboardLogoHref = !empty($accountSuspended) ? '/index.php?jomu_suspended_browse=1' : '/index.php';
 if (!empty($accountSuspended)) {
    $_SESSION['jomu_suspended_browse'] = true;
    $_SESSION['jomu_suspended_until'] = trim((string) ($dashboardUser['inactive_until'] ?? ''));
 }
?>

<body class="vendor-dashboard-page<?php echo !empty($accountSuspended) ? ' account-suspended' : ''; ?>">
    <?php if (!empty($accountSuspended)): ?>
    <div class="vendor-suspension-overlay" role="dialog" aria-modal="true" aria-labelledby="vendorSuspensionTitle">
        <div class="vendor-suspension-panel">
            <h2 id="vendorSuspensionTitle">Account suspended</h2>
            <p>This business account has been suspended until <?php echo htmlspecialchars($suspensionUntilLabel !== '' ? $suspensionUntilLabel : 'the date set by JoMu Admin'); ?>.</p>
        </div>
    </div>
    <?php endif; ?>
    <header>
        <nav class="navbar navbar-expand-lg navbar-light fixed-top navbarone navbar-help bg-white" id="navbarone">
            <div class="vendor-dashboard-brand-wrap">
                <a class="navbar-brand brand-logos" href="<?php echo htmlspecialchars($dashboardLogoHref); ?>">
                    <img src="/assets/images/JoMu logo redesigned.png" class="img-fluid logo">
                </a>
            </div>
            <div class="vendor-dashboard-title-wrap">
                <h3 class="vendor-dashboard-title">Vendor Dashboard</h3>
            </div>

            <!-- Toggler for small and medium screens -->
            <div class="dropdown d-lg-none dashboard-mobile-auth-dropdown">
                <button class="btn dashboard-mobile-auth-trigger" type="button" id="dashboardMobileAuthMenuButton"
                    aria-expanded="false" aria-label="Open account menu">
                    <img src="\assets\images\icons\Profile - Sign out Icon.png" class="dashboard-mobile-auth-icon" alt="Account menu">
                </button>
                <div class="dropdown-menu dropdown-menu-end mobile-auth-menu" aria-labelledby="dashboardMobileAuthMenuButton">
                    <div class="offcanvas-body">
                        <?php if (!empty($accountSuspended)) { ?>
                        <button class="button button-createaccount vendor-dashboard-suspended-disabled" style="min-width: 100px;" type="button" disabled>Profile</button>
                        <?php } else { ?>
                        <button class="button button-createaccount" style="min-width: 100px;" onclick="location.href='profile.php'">Profile</button>
                        <?php } ?>
                        <!-- <hr> -->
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item active">
                                <a class="nav-link link-text js-signout-link" href="/php/auth/signout.php" style="color: rgb(0,0,255); margin-left:18px;">Sign Out</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- Navbar links for
             large screens -->
            <div class="collapse navbar-collapse d-none d-lg-flex me-4 links-container" id="navbarNav">
                <?php if (!empty($accountSuspended)) { ?>
                <button class="button button-createaccount vendor-dashboard-suspended-disabled" type="button" disabled>Profile</button>
                <?php } else { ?>
                <button class="button button-createaccount" onclick="location.href='profile.php'">Profile</button>
                <?php } ?>

                <ul class="navbar-nav">
                    <li class="nav-item active signin">
                        <a class="nav-link link-text js-signout-link" href="/php/auth/signout.php" style="color: rgb(0, 0, 255);">Sign Out</a>
                    </li>
                </ul>
            </div>
            <!-- offcanvas menu for small and medium screens -->
            <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="offcanvasNav"
                aria-labelledby="offcanvasNavbarLabel" style="height: 20vh; background-color: white;">
                <div class="offcanvas-header">
                    <button type="button" class="btn-close bg-white " data-bs-dismiss="offcanvas"
                        aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <?php if (!empty($accountSuspended)) { ?>
                    <button class="button button-createaccount vendor-dashboard-suspended-disabled" type="button" disabled>Profile</button>
                    <?php } else { ?>
                    <button class="button button-createaccount" onclick="location.href='profile.php'">Profile</button>
                    <?php } ?>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item active">
                            <a class="nav-link link-text js-signout-link" href="/php/auth/signout.php" style="color: rgb(0, 0, 255);">Sign Out</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div id="main-navbar-chat-mode" class="container-fluid px-0 main-navbar-chat-mode">
                <button type="button" id="main-nav-chat-back" class="main-navbar-chat-back" aria-label="Back to inbox list">&larr;</button>
                <a id="main-nav-chat-profile-link" class="main-navbar-chat-profile" href="#" aria-disabled="true">
                    <img id="main-nav-chat-profilepic" src="/assets/images/profile.png" class="main-navbar-chat-profilepic" alt="Business profile">
                    <span id="main-nav-chat-business-name" class="main-navbar-chat-business-name">Business</span>
                </a>
            </div>
        </nav>
        <nav id="dashboard-secondary-nav" class="navbar navbar-expand-lg navbar-light navbartw bg-dark">
            <div class="containerone-vendordashboard text-center">
                <div class="dropdown">
                    <button class="button nav-options hover-underline filterpointvendorshops dashboard-nav-btn" id="friday"
                        onclick="showDashboardSection('listings')">Manage Listings</button>
                </div>

                <div class="dropdown">
                    <button
                        class="button nav-options hover-underline filterpointvendorshops dashboard-nav-btn"
                        id="manage-purchases-tab-btn"
                        onclick="showDashboardSection('purchases')">
                        <?php echo htmlspecialchars($manageRequestsLabel); ?>
                        <span
                            id="manage-purchases-badge"
                            class="dashboard-tab-badge<?php echo $purchaseBadgeCount > 0 ? ' visible' : ''; ?>"
                        ><?php echo $purchaseBadgeCount > 99 ? '99+' : (int) $purchaseBadgeCount; ?></span>
                    </button>
                </div>
                <div class="dropdown">
                    <button
                        class="button nav-options hover-underline filterpointvendorshops dashboard-nav-btn"
                        id="inbox-tab-btn"
                        onclick="showDashboardSection('inbox')"
                    >
                        Inbox
                        <span
                            id="inbox-tab-badge"
                            class="dashboard-tab-badge<?php echo $inboxBadgeCount > 0 ? ' visible' : ''; ?>"
                        ><?php echo $inboxBadgeCount > 99 ? '99+' : (int) $inboxBadgeCount; ?></span>
                    </button>
                </div>
            </div>
        </nav>
    </header>

    <!-- Manage Listings Section  -->
    <main id="dashboard-listings">
        <div class="">
            <h3 class="text-center bg-dark py-2" style="color: white; position: sticky; top: 65px; z-index: 10;">
                My Listings
                <!-- <?php // echo $_SESSION['emailormobilenumber']; ?> -->
            </h3>
        </div>
        <div class="container my-2 cards-container dashboard-listings-shell">
            <div class="row g-1 justify-content-center mb-2">
                <div class="col-auto add-listing-card-wrap">
                    <div class="card add-listing-card" onclick="location.href='addnewlisting.php'" role="button" tabindex="0" style="cursor: pointer;">
                        <img src="/assets/images/icons/Add listing icon.png" class="card-img-top img-fluid add-listing-card-icon" alt="Add listing" draggable="false">
                        <div class="card text-center add-listing-card-caption">
                            <p class="add-listing-card-label mb-0">Add new listing</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center">
                <h5 style="color: rgb(241,90,36);">My Listings</h5>
            </div>
            <div class="row g-1 dashboard-showroom-grid showroom-container">
                <?php foreach ($dashboardListings as $listing): ?>
                    <?php
                        $media = (string) ($listing['media'] ?? '');
                        $type = getMediaType($media);
                        $viewsLabel = formatListingViewsLabel($listing['views'] ?? 0);
                        $isOutOfStock = !empty($listing['out_of_stock']);
                        $isHiddenByAdmin = strtolower((string) ($listing['moderation_status'] ?? 'visible')) === 'hidden';
                        $listingType = strtolower((string) ($listing['listing_type'] ?? ''));
                        if ($listingType !== 'product' && $listingType !== 'service') {
                            $categoryText = strtolower((string) ($listing['category'] ?? ''));
                            $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                        }
                        $priceFrom = trim((string) ($listing['price_from'] ?? ''));
                        $priceTo = trim((string) ($listing['price_to'] ?? ''));
                        $productPriceLabel = '';
                        if ($listingType === 'product' && $priceFrom !== '' && $priceTo !== '') {
                            $productPriceLabel = formatProductPriceRange($priceFrom, $priceTo);
                        } elseif ($listingType === 'product') {
                            $productPriceLabel = formatPriceText(trim((string) ($listing['price'] ?? '')));
                        }
                        $shareParams = http_build_query([
                            'image' => getMediaPath($media, ''),
                            'title' => $listing['stockname'] ?? '',
                            'price' => $productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? ''),
                            'raw_price' => $listing['price'] ?? '',
                            'price_from' => $priceFrom,
                            'price_to' => $priceTo,
                            'description' => $listing['description'] ?? '',
                            'category' => $listing['category'] ?? '',
                            'seller_businessname' => '',
                            'seller_profilepic' => '',
                            'seller_id' => $listing['user_id'] ?? '',
                            'listing_id' => $listing['listing_id'] ?? '',
                            'listing_type' => $listingType,
                        ]);
                        $previewTitle = trim((string) ($listing['stockname'] ?? ''));
                        $previewDescription = trim((string) ($listing['description'] ?? ''));
                        $previewPrice = trim((string) ($productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? '')));
                    ?>
                    <div class="col-4 col-md-4 col-lg-3">
                        <div class="card h-100 showroom-img<?php echo $isOutOfStock ? ' owner-out-of-stock-card' : ''; ?><?php echo $isHiddenByAdmin ? ' owner-hidden-card' : ''; ?>">
                            <?php if ($isHiddenByAdmin): ?>
                                <span class="owner-hidden-badge">Hidden</span>
                            <?php endif; ?>
                            <div class="card-options dropdown">
                                <img src="/assets/images/icons/Dots icons.png" class="img-fluid options-icons dots-icon manage-listing-options-trigger" role="button" tabindex="0" aria-expanded="false" alt="Listing options">
                                <ul class="dropdown-content"
                                    style="width: 120px; height: auto; top: 80px;left: -90px; padding: 10px; color: black;"
                                    aria-labelledby="dotsoptions">
                                    <?php if (!$isHiddenByAdmin): ?>
                                    <li class="li-1">
                                        <a
                                            class="dropdown-item manage-listing-share"
                                            href="#"
                                            data-share-url="/purchasewholesale.html?<?php echo htmlspecialchars($shareParams); ?>"
                                        >Share</a>
                                    </li>
                                    <?php endif; ?>
                                    <li class="li-1">
                                        <a
                                            class="dropdown-item manage-listing-delete"
                                            href="#"
                                            data-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>"
                                        >Delete</a>
                                    </li>
                                </ul>
                            </div>

                            <div class="showroom-media-frame">
                                <?php if ($type === 'video'): ?>
                                    <div class="video-wrapper">
                                        <video class="video-content media-preview-source" controls muted data-preview-type="video" data-preview-src="<?php echo htmlspecialchars(getMediaPath($media, '')); ?>" data-preview-title="<?php echo htmlspecialchars($previewTitle); ?>" data-preview-description="<?php echo htmlspecialchars($previewDescription); ?>" data-preview-price="<?php echo htmlspecialchars($previewPrice); ?>" data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">
                                            <source src="<?php echo htmlspecialchars(getMediaPath($media, '')); ?>">
                                        </video>
                                    </div>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars(getMediaPath($media, '')); ?>" class="card-img-showroom img-fluid media-preview-source" alt="<?php echo htmlspecialchars((string) ($listing['stockname'] ?? 'Listing')); ?>" data-preview-type="image" data-preview-src="<?php echo htmlspecialchars(getMediaPath($media, '')); ?>" data-preview-title="<?php echo htmlspecialchars($previewTitle); ?>" data-preview-description="<?php echo htmlspecialchars($previewDescription); ?>" data-preview-price="<?php echo htmlspecialchars($previewPrice); ?>" data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">
                                <?php endif; ?>

                                <div class="card-views">
                                    <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
                                    <p data-listing-view-label="<?php echo (int) ($listing['listing_id'] ?? 0); ?>"><?php echo htmlspecialchars($viewsLabel); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($dashboardListings)): ?>
                    <div class="col-12">
                        <h5 class="text-center">No Listings Found!</h5>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <div class="modal fade" id="deleteListingModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Listing</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="proceed-delete-btn" class="btn" style="background-color: red; color: white;">Proceed</button>
                        <button type="button" id="decline-delete-btn" class="btn" style="background-color: green; color: white;">Decline</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="dashboardMediaPreviewOverlay" aria-hidden="true">
            <button type="button" class="dashboard-media-preview-close" id="dashboardMediaPreviewClose" aria-label="Close preview">&times;</button>
            <div class="dashboard-media-preview-panel">
                <img id="dashboardMediaPreviewImage" class="dashboard-media-preview-content" alt="Listing preview" style="display:none;">
                <video id="dashboardMediaPreviewVideo" class="dashboard-media-preview-content" controls style="display:none;"></video>
                <div id="dashboardMediaPreviewDetails" class="dashboard-media-preview-details" style="display:none;">
                    <p id="dashboardMediaPreviewTitle" class="dashboard-media-preview-title"></p>
                    <p id="dashboardMediaPreviewPrice" class="dashboard-media-preview-price"></p>
                    <p id="dashboardMediaPreviewDescription" class="dashboard-media-preview-description"></p>
                </div>
            </div>
        </div>
    </main>

    <!-- Manage Purchases Section  -->
    <main id="dashboard-purchases" style="display: none;">
        <div class="">
            <h3 class="text-center bg-dark py-2" style="color: white; position: sticky; top: 65px; z-index: 10;">
                <?php echo htmlspecialchars($purchasesSectionTitle); ?>
            </h3>
            <hr>
        </div>
        <div class="container my-2 cards-container dashboard-purchases-shell">
            <h5><u><?php echo htmlspecialchars($purchasesListingsHeading); ?></u></h5>
            <?php if (empty($purchaseRequestsByListing)): ?>
                <h6 class="mt-3" id="purchase-empty-state">No purchase or schedule requests yet.</h6>
            <?php else: ?>
                <?php foreach ($purchaseRequestsByListing as $listingId => $listingRequests): ?>
                    <?php
                        $firstReq = $listingRequests[0];
                        $listingMedia = trim((string) ($firstReq['listing_media'] ?? ''));
                        $listingMediaPath = getMediaPath($listingMedia, '');
                        $hasListingMedia = $listingMediaPath !== '';
                        $listingStockName = trim((string) ($firstReq['listing_stockname'] ?? 'Listing'));
                        $listingIsOutOfStock = !empty($firstReq['out_of_stock']);
                        $listingType = strtolower((string) ($firstReq['listing_listing_type'] ?? $firstReq['listing_type'] ?? 'product'));
                        if ($listingType !== 'service' && $listingType !== 'product') {
                            $categoryText = strtolower((string) ($firstReq['listing_category'] ?? ''));
                            $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                        }
                        $listingPriceFrom = trim((string) ($firstReq['listing_price_from'] ?? ''));
                        $listingPriceTo = trim((string) ($firstReq['listing_price_to'] ?? ''));
                        if ($listingType === 'product' && $listingPriceFrom !== '' && $listingPriceTo !== '') {
                            $listingPriceLabel = formatProductPriceRange($listingPriceFrom, $listingPriceTo);
                        } else {
                            $rawListingPrice = trim((string) ($firstReq['listing_price'] ?? ''));
                            if ($listingType === 'service') {
                                $listingPriceLabel = $rawListingPrice !== '' ? ('Charge: ' . $rawListingPrice) : 'Service';
                            } else {
                                $listingPriceLabel = $rawListingPrice !== '' ? $rawListingPrice : 'Price on request';
                            }
                        }
                        $purchasePreviewDescription = trim((string) ($firstReq['listing_description'] ?? ''));
                        $headingLabel = $listingType === 'service' ? 'Businesses that did schedule.' : 'Businesses that did purchase.';
                        $listingDeleted = !$hasListingMedia;
                    ?>
                    <div class="mt-1 purchases-container">
                        <div class="col-12 col-md-4 col-lg-3">
                            <div class="card h-100 text-center purchase-listing-card<?php echo $listingIsOutOfStock ? ' owner-out-of-stock-card' : ''; ?>" data-listing-deleted="<?php echo $listingDeleted ? '1' : '0'; ?>">
                                <?php if ($hasListingMedia): ?>
                                    <img
                                        src="<?php echo htmlspecialchars($listingMediaPath); ?>"
                                        class="card-img-top img-fluid purchase-listing-media media-preview-source"
                                        alt="<?php echo htmlspecialchars($listingStockName); ?>"
                                        data-preview-type="image"
                                        data-preview-src="<?php echo htmlspecialchars($listingMediaPath); ?>"
                                        data-preview-title="<?php echo htmlspecialchars($listingStockName); ?>"
                                        data-preview-description="<?php echo htmlspecialchars($purchasePreviewDescription); ?>"
                                        data-preview-price="<?php echo htmlspecialchars($listingPriceLabel); ?>"
                                        data-preview-listing-id="<?php echo (int) $listingId; ?>">
                                <?php else: ?>
                                    <div class="card-img-top purchase-listing-media purchase-listing-media-missing">This listing was deleted.</div>
                                <?php endif; ?>
                                <div class="card-body purchase-listing-info">
                                    <h5 class="card-title purchase-preview-trigger"><?php echo htmlspecialchars($listingStockName); ?></h5>
                                    <p class="card-text purchase-preview-trigger"><?php echo htmlspecialchars($listingPriceLabel); ?></p>
                                    <?php if ($listingType === 'product'): ?>
                                        <button
                                            type="button"
                                            class="btn bg-danger mark-out-of-stock-btn"
                                            style="color: white;"
                                            data-listing-id="<?php echo (int) $listingId; ?>"
                                            data-out-of-stock="<?php echo $listingIsOutOfStock ? '1' : '0'; ?>"
                                            data-listing-deleted="<?php echo $listingDeleted ? '1' : '0'; ?>"
                                            <?php echo $listingDeleted ? 'disabled' : ''; ?>
                                        ><?php echo $listingIsOutOfStock ? 'Out of Stock.' : 'Mark as out of stock'; ?></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="container">
                            <h5><?php echo htmlspecialchars($headingLabel); ?></h5>
                            <hr>
                            <?php foreach ($listingRequests as $request): ?>
                                <?php
                                    $buyerName = trim((string) ($request['buyer_businessname'] ?? 'Business'));
                                    if ($buyerName === '') {
                                        $buyerName = 'Business';
                                    }
                                    $buyerUserId = (int) ($request['buyer_user_id'] ?? 0);
                                    $buyerProfileUrl = '/php/visitor_profile.php?user_id=' . $buyerUserId;
                                    $buyerProfilePic = trim((string) ($request['buyer_profilepic'] ?? ''));
                                    $buyerPicPath = $buyerProfilePic !== '' ? getMediaPath($buyerProfilePic, '') : '/assets/images/profile.png';
                                    $timeAgo = formatRequestTimeAgo(
                                        $request['created_at'] ?? '',
                                        $request['current_db_ts'] ?? null,
                                        $request['request_created_ts'] ?? null
                                    );
                                    $amountLabel = $listingType === 'service' ? 'Service Requirement:' : 'Amount:';
                                    $deliveryLabel = $listingType === 'service' ? 'Timeline:' : 'Delivery Method:';
                                ?>
                                <?php
                                    $requestStatus = strtolower(trim((string) ($request['status'] ?? 'pending')));
                                    $isPendingRequest = $requestStatus === '' || $requestStatus === 'pending';
                                ?>
                                <div class="comment-section-bulk-orders<?php echo $isPendingRequest ? ' pending-request-highlight' : ''; ?>">
                                    <a href="<?php echo htmlspecialchars($buyerProfileUrl); ?>" aria-label="View <?php echo htmlspecialchars($buyerName); ?> profile">
                                        <img src="<?php echo htmlspecialchars($buyerPicPath); ?>" class="img-fluid comment-pic-bulk-orders">
                                    </a>
                                    <div class="profile-name-comment mt-3">
                                        <h4 class="mb-0"><a href="<?php echo htmlspecialchars($buyerProfileUrl); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($buyerName); ?></a></h4>
                                        <p class="mt-0 comment-time"><?php echo htmlspecialchars($timeAgo); ?></p>

                                        <div class="container-fluid">
                                            <div class="about-purchase">
                                                <p class="mb-0 mt-0 about-purchase-field<?php echo $listingType === 'service' ? ' service-requirement-field' : ''; ?>">
                                                    <b><?php echo htmlspecialchars($amountLabel); ?></b>
                                                    <span class="about-purchase-value"><?php echo htmlspecialchars((string) ($request['amount'] ?? '')); ?></span>
                                                </p>
                                                <p class="mb-0 mt-0 about-purchase-field">
                                                    <b>Mode of Payment:</b>
                                                    <span class="about-purchase-value"><?php echo htmlspecialchars((string) ($request['payment_mode'] ?? '')); ?></span>
                                                </p>
                                                <p class="mb-0 mt-0 about-purchase-field">
                                                    <b><?php echo htmlspecialchars($deliveryLabel); ?></b>
                                                    <span class="about-purchase-value"><?php echo htmlspecialchars((string) ($request['delivery_method'] ?? '')); ?></span>
                                                </p>
                                                <p class="mb-0 mt-0 about-purchase-field">
                                                    <b>Location:</b>
                                                    <span class="about-purchase-value"><?php echo htmlspecialchars((string) ($request['location'] ?? '')); ?></span>
                                                </p>
                                            </div>

                                            <div class="mt-2 purchase-request-actions">
                                                <?php
                                                    $listingSharePath = $listingDeleted
                                                        ? '/purchasewholesale.html?' . http_build_query([
                                                            'listing_not_found' => '1',
                                                            'seller_businessname' => (string) ($dashboardUser['businessname'] ?? ''),
                                                            'seller_profilepic' => (string) ($dashboardUser['profilepic'] ?? ''),
                                                            'seller_id' => (int) ($dashboardUser['id'] ?? 0),
                                                            'listing_id' => (int) $listingId,
                                                            'listing_type' => $listingType,
                                                            'request_view' => '1'
                                                        ])
                                                        : '/purchasewholesale.html?' . http_build_query([
                                                            'image' => formatInboxMediaPath($listingMedia),
                                                            'title' => $listingStockName,
                                                            'price' => $listingPriceLabel,
                                                            'raw_price' => (string) ($firstReq['listing_price'] ?? ''),
                                                            'price_from' => $listingPriceFrom,
                                                            'price_to' => $listingPriceTo,
                                                            'description' => (string) ($firstReq['listing_description'] ?? ''),
                                                            'category' => (string) ($firstReq['listing_category'] ?? ''),
                                                            'seller_businessname' => (string) ($dashboardUser['businessname'] ?? ''),
                                                            'seller_profilepic' => (string) ($dashboardUser['profilepic'] ?? ''),
                                                            'seller_id' => (int) ($dashboardUser['id'] ?? 0),
                                                            'listing_id' => (int) $listingId,
                                                            'listing_type' => $listingType,
                                                            'request_view' => '1',
                                                            'req_amount' => (string) ($request['amount'] ?? ''),
                                                            'req_payment_mode' => (string) ($request['payment_mode'] ?? ''),
                                                            'req_delivery_method' => (string) ($request['delivery_method'] ?? ''),
                                                            'req_location' => (string) ($request['location'] ?? '')
                                                        ]);
                                                    $listingLabelForMessage = $listingStockName !== '' ? $listingStockName : 'listing';
                                                    $requestActionLabel = $listingType === 'service' ? 'schedule request' : 'purchase request';
                                                ?>
                                                <p
                                                    class="bg-success proceed-btn<?php echo $listingDeleted ? ' proceed-btn-disabled' : ' proceed-contact-btn'; ?>"
                                                    data-listing-deleted="<?php echo $listingDeleted ? '1' : '0'; ?>"
                                                    data-buyer-id="<?php echo (int) ($request['buyer_user_id'] ?? 0); ?>"
                                                    data-request-id="<?php echo (int) ($request['request_id'] ?? 0); ?>"
                                                    data-buyer-whatsapp="<?php echo htmlspecialchars((string) ($request['buyer_business_contact'] ?? '')); ?>"
                                                    data-listing-url="<?php echo htmlspecialchars($listingSharePath); ?>"
                                                    data-listing-label="<?php echo htmlspecialchars($listingLabelForMessage); ?>"
                                                    data-request-label="<?php echo htmlspecialchars($requestActionLabel); ?>"
                                                >
                                                    Proceed</p>
                                                <p class="bg-danger decline-purchase-btn" style="color: white;"
                                                    data-request-id="<?php echo (int) ($request['request_id'] ?? 0); ?>"
                                                    data-decline-title="<?php echo $listingType === 'service' ? 'Decline Schedule' : 'Decline Purchase'; ?>">
                                                    <?php echo $listingType === 'service' ? 'Decline Schedule' : 'Decline Purchase'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <div class="modal fade" id="declineRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="decline-request-modal-title"><strong>Decline Purchase</strong></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p id="decline-request-modal-message">Declined purchase will be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" id="decline-request-yes-btn" class="btn">Continue</button>
                    <button type="button" id="decline-request-no-btn" class="btn">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="proceedViaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-center w-100" style="font-weight: 800;">Proceed via</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex justify-content-center align-items-center gap-2">
                    <button type="button" id="proceed-via-message-btn" class="btn" style="background-color: rgb(0, 0, 255); color: white; font-weight: 700;">Message</button>
                    <button type="button" id="proceed-via-whatsapp-btn" class="btn" style="background-color: #25D366; color: white; font-weight: 700;">WhatsApp</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="signOutConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-body text-center">
                    <p class="mb-0 fw-bold">Are you sure you want to sign out?</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" id="cancel-signout-btn" class="btn" style="background-color: #198754; color: #fff; font-weight: 700;">Cancel</button>
                    <button type="button" id="confirm-signout-btn" class="btn" style="background-color: #dc3545; color: #fff; font-weight: 700;">Sign out</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteConversationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-body text-center">
                    <p class="mb-0 fw-bold">This chat will be permanently deleted.</p>
                </div>
                <div class="modal-footer justify-content-center" style="gap: 10px;">
                    <button type="button" id="delete-conversation-cancel-btn" class="btn" style="background-color: #198754; color: #fff; font-weight: 700;">Cancel</button>
                    <button type="button" id="delete-conversation-confirm-btn" class="btn" style="background-color: #dc3545; color: #fff; font-weight: 700;">Delete</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Inbox section  -->
    <main id="dashboard-inbox" style="display: none;">
        <!-- <div class="container-fluid">
            <p class="py-2 px-2 mb-0" style=" color: rgb(255, 255, 255); font-weight: 600; font-size: small;"><a
                    href="index.php" style="text-decoration: none; color: rgb(255, 255, 255);">Home Page</a> <span
                    style="color: rgb(0,0,255);">&#187;</span>
                Business<span style="color: rgb(0,0,255);">&#187;</span>Vendor Dashboard
            </p>
        </div> -->


        <!-- Inbox / Messages  -->
        <div class="" id="inbox-list-header">
            <h3 class="text-center bg-dark py-2" style="color: white; position: sticky; top: 65px; z-index: 10;">Inbox
            </h3>
            <hr>
        </div>

        <div class="container-fluid messages-inbox text-center" id="inbox-conversation-list" style="display: none;"></div>


        <div class="dm-messages-inbox" id="inbox-active-panel">
            <div id="inbox-message-thread"></div>

        </div>

        <!-- Text message area for large screens.  -->
        <div class="container-fluid message-area-div text-center d-none d-md-none d-lg-block bg-white py-1 inbox-chat-only-hidden">
            <div class="inbox-compose-reply-box">
                <div class="inbox-compose-reply-meta">
                    <span class="inbox-reply-preview-label"></span>
                    <span class="inbox-reply-preview-text"></span>
                </div>
                <button type="button" class="inbox-compose-reply-cancel" aria-label="Cancel reply">&times;</button>
            </div>
            <textarea id="message-area-message-large" style="margin-bottom: -25px;" class="message-area-message bg-white inbox-message-input"
                placeholder="Send a message..."></textarea>
            <button type="button" class="btn send-message-btn inbox-send-btn"><span>
                    <img src="/assets/images/icons/Send message icon orange.png" class="img-fluid options-icons-inbox">
                </span></button>
        </div>
        <!-- Text message area for medium screens.  -->
        <div class="container-fluid message-area-div text-center mt-1 d-none d-md-block d-lg-none py-1 inbox-chat-only-hidden">
            <div class="inbox-compose-reply-box">
                <div class="inbox-compose-reply-meta">
                    <span class="inbox-reply-preview-label"></span>
                    <span class="inbox-reply-preview-text"></span>
                </div>
                <button type="button" class="inbox-compose-reply-cancel" aria-label="Cancel reply">&times;</button>
            </div>
            <textarea id="message-area-message-medium" style="margin-bottom: -25px;" class="message-area-message inbox-message-input" placeholder="Send a message..."></textarea>
            <button type="button" class="btn send-message-btn inbox-send-btn"><span>
                    <img src="/assets/images/icons/Send message icon orange.png" class="img-fluid options-icons-inbox">
                </span></button>
        </div>
        <!-- Text message area for small screens.  -->
        <div class="container-fluid message-area-div text-center d-block d-md-none d-lg-none py-1 inbox-chat-only-hidden">
            <div class="inbox-compose-reply-box">
                <div class="inbox-compose-reply-meta">
                    <span class="inbox-reply-preview-label"></span>
                    <span class="inbox-reply-preview-text"></span>
                </div>
                <button type="button" class="inbox-compose-reply-cancel" aria-label="Cancel reply">&times;</button>
            </div>
            <textarea id="message-area-message-small" style="margin-bottom: -15px;" class="message-area-message-small inbox-message-input"
                placeholder="Send a message..."></textarea>
            <button type="button" style="margin-bottom: 16px;" class="btn send-message-btn inbox-send-btn"><span>
                    <img src="/assets/images/icons/Send message icon orange.png"
                        class="img-fluid options-icons-inbox-small">
                </span></button>
        </div>



    </main>
    <div id="dashboardLightPopup" role="status" aria-live="polite"></div>
    <footer class=" footer-feedback py-2 text-center bg-white">
        <div class="footer-links">
            <a href="/termsandconditions.html">Terms of Use</a>
            <a href="/privacypolicy.html">Privacy Policy</a>
            <a href="/help.html">Help</a>
            <a href="/support.html">Support</a>
            <a href="/feedback.html">Give Feedback</a>
            <a href="/about.html">About JoMu</a>
        </div>
        <br>
        <small>&copy; 2026 JoMu. All rights reserved.</small>
    </footer>
    <div id="inbox-message-action-menu" class="inbox-message-action-menu" aria-hidden="true">
        <button type="button" id="inbox-message-reply-btn" class="inbox-message-action-btn" style="color: rgb(241, 90, 36);">
            <span class="inbox-message-action-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 8L4 12L10 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M20 18C19.2 13.8 15.94 12 10 12H4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span>Reply</span>
        </button>
        <button type="button" id="inbox-message-copy-btn" class="inbox-message-action-btn" style="color: rgb(241, 90, 36);">
            <span class="inbox-message-action-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="9" y="8" width="10" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M15 8V6C15 4.9 14.1 4 13 4H7C5.9 4 5 4.9 5 6V14C5 15.1 5.9 16 7 16H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span>Copy</span>
        </button>
        <button type="button" id="inbox-message-delete-btn" class="inbox-message-action-btn">
            <span class="inbox-message-action-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 7H20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M9 4H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M8 10V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M12 10V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M16 10V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M6 7L7 19C7.06 19.7 7.64 20.25 8.34 20.25H15.66C16.36 20.25 16.94 19.7 17 19L18 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span>Delete</span>
        </button>
    </div>
    <div id="inbox-conversation-action-menu" class="inbox-conversation-action-menu" aria-hidden="true">
        <button type="button" id="inbox-conversation-delete-btn" class="inbox-conversation-action-btn">
            <span class="inbox-conversation-action-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 7H20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M9 4H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M8 10V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M12 10V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M16 10V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M6 7L7 19C7.06 19.7 7.64 20.25 8.34 20.25H15.66C16.36 20.25 16.94 19.7 17 19L18 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span>Delete</span>
        </button>
    </div>
    <script>
        function showDashboardSection(section, options = {}) {
            const sectionIds = ['dashboard-listings', 'dashboard-purchases', 'dashboard-inbox'];
            sectionIds.forEach(function (id) {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });

            const targetId = 'dashboard-' + section;
            const target = document.getElementById(targetId);
            if (target) target.style.display = '';
            activeDashboardSection = section;
            if (section === 'inbox') {
                isInboxChatOpen = Boolean(options && options.openConversation);
                refreshInboxUI();
            } else {
                isInboxChatOpen = false;
                refreshInboxUI();
            }
        }

        const inboxConversations = <?php echo json_encode($inboxConversations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const dashboardInitialSection = <?php echo json_encode($initialDashboardSection, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const jomuUserCsrfToken = <?php echo json_encode(jomu_csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const requestedInboxPartnerId = <?php echo (int) $requestedInboxPartnerId; ?>;
        const inboxPrefillParam = new URLSearchParams(window.location.search).get('prefill') || '';
        const dashboardMobileAuthButton = document.getElementById('dashboardMobileAuthMenuButton');
        const dashboardMobileAuthMenu = document.querySelector('.mobile-auth-menu');
        const dashboardInboxEl = document.getElementById('dashboard-inbox');
        const dashboardMainNavbarEl = document.getElementById('navbarone');
        const dashboardSecondaryNavEl = document.getElementById('dashboard-secondary-nav');
        const mainNavChatBackEl = document.getElementById('main-nav-chat-back');
        const mainNavChatProfileLinkEl = document.getElementById('main-nav-chat-profile-link');
        const mainNavChatProfilepicEl = document.getElementById('main-nav-chat-profilepic');
        const mainNavChatBusinessNameEl = document.getElementById('main-nav-chat-business-name');
        const inboxListHeaderEl = document.getElementById('inbox-list-header');
        const inboxConversationListEl = document.getElementById('inbox-conversation-list');
        const inboxActivePanelEl = document.getElementById('inbox-active-panel');
        const inboxActiveProfileLinkEl = document.getElementById('inbox-active-profile-link');
        const inboxActiveProfilepicEl = document.getElementById('inbox-active-profilepic');
        const inboxActiveBusinessNameEl = document.getElementById('inbox-active-business-name');
        const inboxMessageThreadEl = document.getElementById('inbox-message-thread');
        const inboxMessageActionMenuEl = document.getElementById('inbox-message-action-menu');
        const inboxMessageReplyBtnEl = document.getElementById('inbox-message-reply-btn');
        const inboxMessageCopyBtnEl = document.getElementById('inbox-message-copy-btn');
        const inboxMessageDeleteBtnEl = document.getElementById('inbox-message-delete-btn');
        const inboxConversationActionMenuEl = document.getElementById('inbox-conversation-action-menu');
        const inboxConversationDeleteBtnEl = document.getElementById('inbox-conversation-delete-btn');
        const deleteConversationModalEl = document.getElementById('deleteConversationModal');
        const deleteConversationProceedBtnEl = document.getElementById('delete-conversation-cancel-btn');
        const deleteConversationConfirmBtnEl = document.getElementById('delete-conversation-confirm-btn');
        const inboxMessageInputs = Array.from(document.querySelectorAll('.inbox-message-input'));
        const inboxSendButtons = Array.from(document.querySelectorAll('.inbox-send-btn'));
        const inboxComposeReplyBoxes = Array.from(document.querySelectorAll('.inbox-compose-reply-box'));
        const proceedViaModalEl = document.getElementById('proceedViaModal');
        const proceedViaMessageBtn = document.getElementById('proceed-via-message-btn');
        const proceedViaWhatsappBtn = document.getElementById('proceed-via-whatsapp-btn');
        const signOutConfirmModalEl = document.getElementById('signOutConfirmModal');
        const cancelSignOutBtn = document.getElementById('cancel-signout-btn');
        const confirmSignOutBtn = document.getElementById('confirm-signout-btn');
        const signOutLinks = Array.from(document.querySelectorAll('.js-signout-link'));
        const dashboardLightPopupEl = document.getElementById('dashboardLightPopup');
        const proceedContactButtons = Array.from(document.querySelectorAll('.proceed-contact-btn'));
        const declinePurchaseButtons = Array.from(document.querySelectorAll('.decline-purchase-btn'));
        const managePurchasesBadgeEl = document.getElementById('manage-purchases-badge');
        const inboxTabBadgeEl = document.getElementById('inbox-tab-badge');
        let dashboardBadgePollTimer = null;
        let activeConversationPartnerId = inboxConversations.length ? Number.parseInt(inboxConversations[0].partner_id || 0, 10) : 0;
        let lastReadSyncedPartnerId = 0;
        if (requestedInboxPartnerId > 0) {
            activeConversationPartnerId = requestedInboxPartnerId;
        }
        let activeDashboardSection = 'listings';
        let isInboxChatOpen = false;
        let proceedViaModal = null;
        let signOutConfirmModal = null;
        let pendingSignOutHref = '/php/auth/signout.php';
        let dashboardLightPopupTimer = null;
        let selectedProceedPayload = null;
        let pendingInboxPrefill = inboxPrefillParam;
        let activeMessageActionMessageId = 0;
        let activeMessageActionDirection = '';
        let longPressMessageTimer = null;
        let activeConversationActionPartnerId = 0;
        let longPressConversationTimer = null;
        let suppressConversationItemClickUntil = 0;
        let deleteConversationModal = null;

        function setDashboardMobileAuthOpen(isOpen) {
            if (!(dashboardMobileAuthMenu instanceof HTMLElement) || !(dashboardMobileAuthButton instanceof HTMLElement)) {
                return;
            }

            dashboardMobileAuthMenu.classList.toggle('show', isOpen);
            dashboardMobileAuthButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        if (dashboardMobileAuthButton && dashboardMobileAuthMenu) {
            dashboardMobileAuthButton.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                setDashboardMobileAuthOpen(!dashboardMobileAuthMenu.classList.contains('show'));
            });

            document.addEventListener('click', (event) => {
                if (dashboardMobileAuthButton.contains(event.target) || dashboardMobileAuthMenu.contains(event.target)) {
                    return;
                }

                setDashboardMobileAuthOpen(false);
            });
        }
        let pendingDeleteConversationPartnerId = 0;
        let activeReplyMessageId = 0;
        let shouldStickInboxToBottom = true;

        function escapeInboxHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatInboxMessageBodyHtml(value) {
            const rawText = String(value ?? '');
            const urlPattern = /(https?:\/\/[^\s<>"']+)/gi;
            const parts = [];
            let lastIndex = 0;
            let match;

            while ((match = urlPattern.exec(rawText)) !== null) {
                const matchText = match[0] || '';
                const startIndex = Number(match.index || 0);
                if (startIndex > lastIndex) {
                    parts.push(escapeInboxHtml(rawText.slice(lastIndex, startIndex)));
                }

                let href = matchText;
                try {
                    href = new URL(matchText).toString();
                } catch (error) {
                    href = matchText;
                }

                parts.push(`<a href="${escapeInboxHtml(href)}" target="_blank" rel="noopener noreferrer">${escapeInboxHtml(matchText)}</a>`);
                lastIndex = startIndex + matchText.length;
            }

            if (lastIndex < rawText.length) {
                parts.push(escapeInboxHtml(rawText.slice(lastIndex)));
            }

            return parts.join('');
        }

        function parseInboxDate(dateValue) {
            if (!dateValue) return null;
            const normalized = String(dateValue).replace(' ', 'T');
            const parsedDate = new Date(normalized);
            return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
        }

        function formatInboxListTime(dateValue) {
            const parsedDate = parseInboxDate(dateValue);
            if (!parsedDate) return '';

            const seconds = Math.max(0, Math.floor((Date.now() - parsedDate.getTime()) / 1000));
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
            if (seconds < 86400) return `${Math.floor(seconds / 3600)} hr ago`;
            if (seconds < 2592000) {
                const days = Math.floor(seconds / 86400);
                return `${days} day${days === 1 ? '' : 's'} ago`;
            }
            if (seconds < 31536000) {
                const months = Math.floor(seconds / 2592000);
                return `${months} month${months === 1 ? '' : 's'} ago`;
            }
            const years = Math.floor(seconds / 31536000);
            return `${years} year${years === 1 ? '' : 's'} ago`;
        }

        function formatInboxMessageTimestamp(dateValue) {
            const parsedDate = parseInboxDate(dateValue);
            if (!parsedDate) return '';

            const months = ['Jan.', 'Feb.', 'Mar.', 'Apr.', 'May.', 'Jun.', 'Jul.', 'Aug.', 'Sep.', 'Oct.', 'Nov.', 'Dec.'];
            const day = parsedDate.getDate();
            const month = months[parsedDate.getMonth()] || '';
            const oneYearAgo = new Date();
            oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
            const showYear = parsedDate.getTime() <= oneYearAgo.getTime();
            let hours = parsedDate.getHours();
            const minutes = String(parsedDate.getMinutes()).padStart(2, '0');
            const meridiem = hours >= 12 ? 'pm' : 'am';
            hours = hours % 12 || 12;
            return showYear
                ? `${day} ${month} ${parsedDate.getFullYear()}. ${hours}:${minutes}${meridiem}`
                : `${day} ${month} ${hours}:${minutes}${meridiem}`;
        }

        function getConversationByPartnerId(partnerId) {
            return inboxConversations.find((conversation) => Number.parseInt(conversation.partner_id || 0, 10) === partnerId) || null;
        }

        function sortInboxConversations() {
            inboxConversations.sort((left, right) => {
                const leftTime = parseInboxDate(left.latest_created_at)?.getTime() || Number(left.sort_timestamp || 0);
                const rightTime = parseInboxDate(right.latest_created_at)?.getTime() || Number(right.sort_timestamp || 0);
                return rightTime - leftTime;
            });
        }

        function getBriefInboxPreview(value) {
            const raw = String(value ?? '').replace(/\s+/g, ' ').trim();
            if (raw === '') return 'No messages yet.';
            const maxChars = 34;
            return raw.length > maxChars ? `${raw.slice(0, maxChars)}...` : raw;
        }

        function getInboxEmptyStateHtml(title = 'Inbox is empty', message = 'Chats with other businesses will appear here.') {
            return `
                <div class="inbox-empty-state">
                    <strong>${escapeInboxHtml(title)}</strong>
                    <p>${escapeInboxHtml(message)}</p>
                </div>
            `;
        }

        function renderInboxConversationList() {
            if (!inboxConversationListEl) return;

            if (isInboxChatOpen) {
                inboxConversationListEl.style.display = 'none';
                return;
            }

            if (!inboxConversations.length) {
                inboxConversationListEl.style.display = '';
                inboxConversationListEl.innerHTML = getInboxEmptyStateHtml();
                return;
            }

            inboxConversationListEl.style.display = '';
            inboxConversationListEl.innerHTML = inboxConversations.map((conversation, index) => {
                const partnerId = Number.parseInt(conversation.partner_id || 0, 10);
                const unreadCount = Math.max(0, Number.parseInt(conversation.unread_count || 0, 10) || 0);
                const itemClass = index === 0 ? 'mb-2' : '';
                return `
                    <div class="${itemClass} inbox-conversation-item" data-partner-id="${partnerId}" style="cursor:pointer;">
                        <img src="${escapeInboxHtml(conversation.partner_profilepic || '/assets/images/profile.png')}" class="img-fluid inbox-list-dp${conversation.is_system_conversation ? ' inbox-list-dp--system' : ''}" onerror="this.onerror=null;this.src='/assets/images/profile.png';">
                        <div class="profile-name-comment inbox-conversation-meta">
                            <h5 class="inbox-conversation-name">${escapeInboxHtml(conversation.partner_name || 'Business')}</h5>
                            <div class="inbox-preview-row">
                                <p class="inbox-preview-text">${escapeInboxHtml(getBriefInboxPreview(conversation.preview_text || 'No messages yet.'))}</p>
                                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
                                    ${unreadCount > 0 ? `<span style="min-width:22px;padding:1px 7px;border-radius:999px;background:rgb(241, 90, 36);color:#fff;font-size:12px;line-height:18px;text-align:center;font-weight:600;">${escapeInboxHtml(unreadCount >= 100 ? '100+' : String(unreadCount))}</span>` : ''}
                                    <p class="inbox-conversation-time mb-0">${escapeInboxHtml(conversation.latest_created_at ? formatInboxListTime(conversation.latest_created_at) : '')}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderInboxActiveConversation() {
            if (!inboxActivePanelEl || !inboxMessageThreadEl) return;

            if (!isInboxChatOpen) {
                inboxActivePanelEl.style.display = 'none';
                return;
            }

            inboxActivePanelEl.style.display = '';

            const activeConversation = getConversationByPartnerId(activeConversationPartnerId);
            if (!activeConversation) {
                if (inboxActiveBusinessNameEl) {
                    inboxActiveBusinessNameEl.textContent = 'No conversation selected';
                }
                if (inboxActiveProfilepicEl) {
                    inboxActiveProfilepicEl.src = '/assets/images/profile.png';
                }
                if (inboxActiveProfileLinkEl) {
                    inboxActiveProfileLinkEl.href = '#';
                    inboxActiveProfileLinkEl.setAttribute('aria-disabled', 'true');
                }
                if (mainNavChatBusinessNameEl) {
                    mainNavChatBusinessNameEl.textContent = 'Inbox';
                }
                if (mainNavChatProfilepicEl) {
                    mainNavChatProfilepicEl.src = '/assets/images/profile.png';
                }
                if (mainNavChatProfileLinkEl) {
                    mainNavChatProfileLinkEl.href = '#';
                    mainNavChatProfileLinkEl.setAttribute('aria-disabled', 'true');
                }
                inboxMessageThreadEl.innerHTML = inboxConversations.length
                    ? '<p class="text-center mt-3">No messages yet.</p>'
                    : getInboxEmptyStateHtml('Inbox is empty', 'Once a business sends you a new message, the chat will appear here.');
                return;
            }

            const activeBusinessName = activeConversation.partner_name || 'Business';
            const activeBusinessPic = activeConversation.partner_profilepic || '/assets/images/profile.png';
            const isSystemConversation = !!activeConversation.is_system_conversation;
            const activeBusinessLink = isSystemConversation ? '#' : `/php/visitor_profile.php?user_id=${Number.parseInt(activeConversation.partner_id || 0, 10)}`;
            const toggleInboxProfilePicFit = (imgEl) => {
                if (!imgEl) {
                    return;
                }
                imgEl.classList.toggle('inbox-list-dp--system', isSystemConversation);
                imgEl.classList.toggle('main-navbar-chat-profilepic--system', isSystemConversation);
                imgEl.classList.toggle('inbox-profilepic--system', isSystemConversation);
            };
            if (inboxActiveBusinessNameEl) {
                inboxActiveBusinessNameEl.textContent = activeBusinessName;
            }
            if (mainNavChatBusinessNameEl) {
                mainNavChatBusinessNameEl.textContent = activeBusinessName;
            }
            if (inboxActiveProfilepicEl) {
                toggleInboxProfilePicFit(inboxActiveProfilepicEl);
                inboxActiveProfilepicEl.src = activeBusinessPic;
                inboxActiveProfilepicEl.onerror = () => {
                    inboxActiveProfilepicEl.onerror = null;
                    inboxActiveProfilepicEl.src = '/assets/images/profile.png';
                };
            }
            if (inboxActiveProfileLinkEl) {
                inboxActiveProfileLinkEl.href = activeBusinessLink;
                if (isSystemConversation) {
                    inboxActiveProfileLinkEl.setAttribute('aria-disabled', 'true');
                } else {
                    inboxActiveProfileLinkEl.removeAttribute('aria-disabled');
                }
            }
            if (mainNavChatProfilepicEl) {
                toggleInboxProfilePicFit(mainNavChatProfilepicEl);
                mainNavChatProfilepicEl.src = activeBusinessPic;
                mainNavChatProfilepicEl.onerror = () => {
                    mainNavChatProfilepicEl.onerror = null;
                    mainNavChatProfilepicEl.src = '/assets/images/profile.png';
                };
            }
            if (mainNavChatProfileLinkEl) {
                mainNavChatProfileLinkEl.href = activeBusinessLink;
                if (isSystemConversation) {
                    mainNavChatProfileLinkEl.setAttribute('aria-disabled', 'true');
                } else {
                    mainNavChatProfileLinkEl.removeAttribute('aria-disabled');
                }
            }

            if (!Array.isArray(activeConversation.messages) || !activeConversation.messages.length) {
                inboxMessageThreadEl.innerHTML = isSystemConversation
                    ? '<p class="text-center mt-3">No JoMu notices yet.</p>'
                    : '<p class="text-center mt-3">No messages yet. Start the conversation.</p>';
                return;
            }

            inboxMessageThreadEl.innerHTML = activeConversation.messages.map((message) => {
                const isOutgoing = message.direction === 'outgoing';
                const wrapperClass = isOutgoing ? 'messages-themselves mt-3' : 'messages-themselves-receiver';
                const bubbleClass = isOutgoing ? 'sender-messages-themselves mt-0 inbox-message-bubble-actionable' : 'receiver-messages-themselves mt-0 inbox-message-bubble-actionable';
                const replyLabel = message.reply_direction === 'outgoing' ? 'You' : activeBusinessName;
                const replyHtml = message.reply_to_message_id
                    ? `<div class="inbox-reply-preview"><span class="inbox-reply-preview-label">${escapeInboxHtml(replyLabel)}</span><span class="inbox-reply-preview-text">${escapeInboxHtml(getReplyPreviewText(message.reply_text || ''))}</span></div>`
                    : '';
                const bodyHtml = formatInboxMessageBodyHtml(message.text || 'Message');

                return `
                    <div class="${wrapperClass}">
                        <p class="sending-date text-center mb-0">${escapeInboxHtml(formatInboxMessageTimestamp(message.created_at || ''))}</p>
                        <div class="${bubbleClass}" data-message-id="${Number.parseInt(message.message_id || 0, 10)}" data-message-direction="${escapeInboxHtml(message.direction || '')}">${replyHtml}${bodyHtml}</div>
                    </div>
                `;
            }).join('');
            if (shouldStickInboxToBottom) {
                scrollInboxToLatestMessage();
            }
        }

        function scrollInboxToLatestMessage() {
            requestAnimationFrame(() => {
                if (!inboxMessageThreadEl) return;
                inboxMessageThreadEl.scrollTo({
                    top: inboxMessageThreadEl.scrollHeight,
                    behavior: 'auto'
                });
            });
        }

        function preserveInboxScrollPosition(renderFn) {
            if (!(inboxMessageThreadEl instanceof HTMLElement)) {
                renderFn();
                return;
            }

            const previousScrollTop = inboxMessageThreadEl.scrollTop;
            const previousScrollHeight = inboxMessageThreadEl.scrollHeight;
            renderFn();

            requestAnimationFrame(() => {
                if (!(inboxMessageThreadEl instanceof HTMLElement)) return;

                const nextScrollHeight = inboxMessageThreadEl.scrollHeight;
                const heightDelta = previousScrollHeight - nextScrollHeight;
                inboxMessageThreadEl.scrollTop = Math.max(0, previousScrollTop - Math.max(0, heightDelta));
            });
        }

        function closeInboxMessageActionMenu() {
            activeMessageActionMessageId = 0;
            activeMessageActionDirection = '';
            if (inboxMessageActionMenuEl instanceof HTMLElement) {
                inboxMessageActionMenuEl.classList.remove('active');
                inboxMessageActionMenuEl.setAttribute('aria-hidden', 'true');
            }
        }

        function openInboxMessageActionMenu(messageId, messageDirection, clientX, clientY) {
            if (!(inboxMessageActionMenuEl instanceof HTMLElement)) return;

            closeInboxConversationActionMenu();
            activeMessageActionMessageId = Number.parseInt(messageId || 0, 10) || 0;
            activeMessageActionDirection = String(messageDirection || '');
            if (!activeMessageActionMessageId) return;

            inboxMessageActionMenuEl.classList.add('active');
            inboxMessageActionMenuEl.setAttribute('aria-hidden', 'false');

            requestAnimationFrame(() => {
                const menuRect = inboxMessageActionMenuEl.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                const nextLeft = Math.min(Math.max(8, clientX), Math.max(8, viewportWidth - menuRect.width - 8));
                const nextTop = Math.min(Math.max(8, clientY), Math.max(8, viewportHeight - menuRect.height - 8));
                inboxMessageActionMenuEl.style.left = `${nextLeft}px`;
                inboxMessageActionMenuEl.style.top = `${nextTop}px`;
            });
        }

        function closeInboxConversationActionMenu() {
            activeConversationActionPartnerId = 0;
            if (inboxConversationActionMenuEl instanceof HTMLElement) {
                inboxConversationActionMenuEl.classList.remove('active');
                inboxConversationActionMenuEl.setAttribute('aria-hidden', 'true');
            }
        }

        function openInboxConversationActionMenu(partnerId, clientX, clientY) {
            if (!(inboxConversationActionMenuEl instanceof HTMLElement)) return;

            closeInboxMessageActionMenu();
            activeConversationActionPartnerId = Number.parseInt(partnerId || 0, 10) || 0;
            if (!activeConversationActionPartnerId) return;

            inboxConversationActionMenuEl.classList.add('active');
            inboxConversationActionMenuEl.setAttribute('aria-hidden', 'false');

            requestAnimationFrame(() => {
                const menuRect = inboxConversationActionMenuEl.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                const nextLeft = Math.min(Math.max(8, clientX), Math.max(8, viewportWidth - menuRect.width - 8));
                const nextTop = Math.min(Math.max(8, clientY), Math.max(8, viewportHeight - menuRect.height - 8));
                inboxConversationActionMenuEl.style.left = `${nextLeft}px`;
                inboxConversationActionMenuEl.style.top = `${nextTop}px`;
            });
        }

        function clearInboxLongPressTimer() {
            if (longPressMessageTimer) {
                window.clearTimeout(longPressMessageTimer);
                longPressMessageTimer = null;
            }
        }

        function clearInboxConversationLongPressTimer() {
            if (longPressConversationTimer) {
                window.clearTimeout(longPressConversationTimer);
                longPressConversationTimer = null;
            }
        }

        function findMessageBubbleFromEventTarget(target) {
            if (!(target instanceof Element)) return null;
            return target.closest('[data-message-id][data-message-direction]');
        }

        function findConversationItemFromEventTarget(target) {
            if (!(target instanceof Element)) return null;
            return target.closest('.inbox-conversation-item');
        }

        function getConversationMessageById(messageId) {
            const safeMessageId = Number.parseInt(messageId || 0, 10);
            if (!safeMessageId) return null;

            const conversation = getConversationByPartnerId(activeConversationPartnerId);
            if (!conversation || !Array.isArray(conversation.messages)) return null;

            return conversation.messages.find((message) => Number.parseInt(message?.message_id || 0, 10) === safeMessageId) || null;
        }

        async function copyInboxMessageText(messageId) {
            const message = getConversationMessageById(messageId);
            const textToCopy = String(message?.text || '').trim();
            if (textToCopy === '') return;

            try {
                await navigator.clipboard.writeText(textToCopy);
            } catch (error) {
                const fallbackTextarea = document.createElement('textarea');
                fallbackTextarea.value = textToCopy;
                fallbackTextarea.setAttribute('readonly', 'readonly');
                fallbackTextarea.style.position = 'fixed';
                fallbackTextarea.style.opacity = '0';
                document.body.appendChild(fallbackTextarea);
                fallbackTextarea.select();
                document.execCommand('copy');
                document.body.removeChild(fallbackTextarea);
            }
        }

        function getReplyPreviewText(value) {
            const raw = String(value ?? '').replace(/\s+/g, ' ').trim();
            if (raw === '') return 'Message';
            return raw.length > 90 ? `${raw.slice(0, 90)}...` : raw;
        }

        function refreshInboxReplyComposer() {
            const replyMessage = getConversationMessageById(activeReplyMessageId);
            const activeConversation = getConversationByPartnerId(activeConversationPartnerId);
            const replyLabel = replyMessage?.direction === 'outgoing'
                ? 'You'
                : (activeConversation?.partner_name || 'Business');
            const replyText = getReplyPreviewText(replyMessage?.text || '');

            inboxComposeReplyBoxes.forEach((boxEl) => {
                if (!(boxEl instanceof HTMLElement)) return;

                const labelEl = boxEl.querySelector('.inbox-reply-preview-label');
                const textEl = boxEl.querySelector('.inbox-reply-preview-text');
                const shouldShow = !!replyMessage;

                boxEl.classList.toggle('active', shouldShow);
                if (labelEl instanceof HTMLElement) {
                    labelEl.textContent = shouldShow ? replyLabel : '';
                }
                if (textEl instanceof HTMLElement) {
                    textEl.textContent = shouldShow ? replyText : '';
                }
            });
        }

        function clearActiveReplyMessage() {
            activeReplyMessageId = 0;
            refreshInboxReplyComposer();
        }

        function setActiveReplyMessage(messageId) {
            const safeMessageId = Number.parseInt(messageId || 0, 10);
            activeReplyMessageId = safeMessageId;
            refreshInboxReplyComposer();

            const firstVisibleInput = inboxMessageInputs.find((inputEl) => inputEl instanceof HTMLElement && inputEl.offsetParent !== null);
            if (firstVisibleInput instanceof HTMLElement) {
                firstVisibleInput.focus();
            }
        }

        function recalculateConversationSummary(conversation) {
            if (!conversation || !Array.isArray(conversation.messages)) return;

            const lastMessage = conversation.messages.length ? conversation.messages[conversation.messages.length - 1] : null;
            if (lastMessage) {
                conversation.preview_text = String(lastMessage.text || '').trim() || 'Message';
                conversation.latest_created_at = lastMessage.created_at || '';
                conversation.latest_time_label = conversation.latest_created_at ? formatInboxListTime(conversation.latest_created_at) : '';
                conversation.sort_timestamp = parseInboxDate(conversation.latest_created_at)?.getTime() || 0;
                return;
            }

            conversation.preview_text = 'No messages yet.';
            conversation.latest_created_at = '';
            conversation.latest_time_label = '';
            conversation.sort_timestamp = 0;
        }

        function removeMessageFromConversation(messageId) {
            const safeMessageId = Number.parseInt(messageId || 0, 10);
            if (!safeMessageId) return false;

            const conversation = getConversationByPartnerId(activeConversationPartnerId);
            if (!conversation || !Array.isArray(conversation.messages)) return false;

            const nextMessages = conversation.messages.filter((message) => Number.parseInt(message?.message_id || 0, 10) !== safeMessageId);
            if (nextMessages.length === conversation.messages.length) return false;

            conversation.messages = nextMessages;
            if (activeReplyMessageId === safeMessageId) {
                clearActiveReplyMessage();
            }
            recalculateConversationSummary(conversation);
            shouldStickInboxToBottom = false;
            preserveInboxScrollPosition(() => {
                refreshInboxUI();
            });
            shouldStickInboxToBottom = true;
            return true;
        }

        function removeConversationFromInbox(partnerId) {
            const safePartnerId = Number.parseInt(partnerId || 0, 10);
            if (!Number.isInteger(safePartnerId) || safePartnerId <= 0) return false;

            const index = inboxConversations.findIndex((conversation) => Number.parseInt(conversation?.partner_id || 0, 10) === safePartnerId);
            if (index < 0) return false;

            inboxConversations.splice(index, 1);

            if (activeConversationPartnerId === safePartnerId) {
                activeConversationPartnerId = inboxConversations.length
                    ? Number.parseInt(inboxConversations[0].partner_id || 0, 10)
                    : 0;
                isInboxChatOpen = false;
                clearActiveReplyMessage();
            }

            refreshInboxUI();
            return true;
        }

        async function deleteInboxMessageForCurrentUser(messageId) {
            const safeMessageId = Number.parseInt(messageId || 0, 10);
            if (!safeMessageId) return;

            try {
                const body = new URLSearchParams();
                body.set('message_id', String(safeMessageId));
                body.set('csrf_token', jomuUserCsrfToken);

                const response = await fetch('delete_business_message_for_me.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });
                if (!response.ok) return;

                const data = await response.json();
                if (!data?.success) return;

                removeMessageFromConversation(safeMessageId);
            } catch (error) {
                // Non-blocking delete action.
            }
        }

        async function deleteInboxConversationForCurrentUser(partnerId) {
            const safePartnerId = Number.parseInt(partnerId || 0, 10);
            if (!Number.isInteger(safePartnerId) || safePartnerId <= 0) return;

            try {
                const body = new URLSearchParams();
                body.set('partner_id', String(safePartnerId));
                body.set('csrf_token', jomuUserCsrfToken);

                const response = await fetch('delete_business_conversation_for_me.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });
                if (!response.ok) return;

                const data = await response.json();
                if (!data?.success) return;

                removeConversationFromInbox(safePartnerId);
                fetchDashboardBadgeCounts();
            } catch (error) {
                // Non-blocking delete action.
            }
        }

        function refreshInboxUI() {
            closeInboxMessageActionMenu();
            closeInboxConversationActionMenu();
            sortInboxConversations();
            if (!getConversationByPartnerId(activeConversationPartnerId) && inboxConversations.length) {
                activeConversationPartnerId = Number.parseInt(inboxConversations[0].partner_id || 0, 10);
            }
            if (activeReplyMessageId && !getConversationMessageById(activeReplyMessageId)) {
                activeReplyMessageId = 0;
            }
            const isInboxSection = activeDashboardSection === 'inbox';
            const isChatPageMode = isInboxSection && isInboxChatOpen;
            dashboardInboxEl?.classList.toggle('inbox-chat-page', isChatPageMode);
            document.body.classList.toggle('inbox-chat-active', isChatPageMode);
            dashboardMainNavbarEl?.classList.toggle('chat-mode', isChatPageMode);
            if (dashboardSecondaryNavEl) {
                dashboardSecondaryNavEl.style.display = isChatPageMode ? 'none' : '';
            }
            if (inboxListHeaderEl) {
                inboxListHeaderEl.classList.toggle('inbox-list-only-hidden', isInboxChatOpen);
            }
            if (inboxConversationListEl) {
                inboxConversationListEl.style.display = isInboxChatOpen ? 'none' : '';
            }
            const activeConversationForComposer = getConversationByPartnerId(activeConversationPartnerId);
            const isSystemConversationForComposer = !!activeConversationForComposer?.is_system_conversation;
            document.querySelectorAll('.message-area-div').forEach((messageAreaEl) => {
                if (messageAreaEl instanceof HTMLElement) {
                    messageAreaEl.classList.toggle('inbox-chat-only-hidden', !isInboxChatOpen || isSystemConversationForComposer);
                }
            });
            renderInboxConversationList();
            renderInboxActiveConversation();
            refreshInboxReplyComposer();
            syncActiveConversationReadState();
            if (isChatPageMode && pendingInboxPrefill) {
                const prefillText = decodeURIComponent(pendingInboxPrefill);
                inboxMessageInputs.forEach((inputEl) => {
                    if (inputEl instanceof HTMLTextAreaElement && inputEl.value.trim() === '') {
                        inputEl.value = prefillText;
                    }
                });
                pendingInboxPrefill = '';
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('prefill');
                window.history.replaceState({}, '', cleanUrl.toString());
            }
        }

        async function appendSentInboxMessage(messagePayload) {
            const partnerId = Number.parseInt(messagePayload.partner_id || 0, 10);
            if (!partnerId) return;

            let conversation = getConversationByPartnerId(partnerId);
            if (!conversation) {
                return;
            }

            conversation.messages = Array.isArray(conversation.messages) ? conversation.messages : [];
            conversation.messages.push({
                message_id: Number.parseInt(messagePayload.message_id || 0, 10),
                direction: 'outgoing',
                type: messagePayload.type || 'text',
                text: messagePayload.text || '',
                media_path: messagePayload.media_path || '',
                reply_to_message_id: Number.parseInt(messagePayload.reply_to_message_id || 0, 10),
                reply_text: messagePayload.reply_text || '',
                reply_direction: messagePayload.reply_direction || 'incoming',
                created_at: messagePayload.created_at || '',
                timestamp_label: messagePayload.timestamp_label || ''
            });
            conversation.preview_text = messagePayload.text || 'Message';
            conversation.latest_created_at = messagePayload.created_at || '';
            conversation.latest_time_label = conversation.latest_created_at ? formatInboxListTime(conversation.latest_created_at) : '';
            conversation.sort_timestamp = parseInboxDate(conversation.latest_created_at)?.getTime() || Date.now();
            activeConversationPartnerId = partnerId;
            clearActiveReplyMessage();
            refreshInboxUI();
        }

        async function sendInboxTextMessage(textValue, textareaEl, buttonEl) {
            const formData = new FormData();
            formData.append('receiver_id', String(activeConversationPartnerId));
            formData.append('message_text', textValue);
            formData.append('csrf_token', jomuUserCsrfToken);
            if (activeReplyMessageId > 0) {
                formData.append('reply_to_message_id', String(activeReplyMessageId));
            }

            buttonEl.disabled = true;

            try {
                const response = await fetch('send_business_message.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    buttonEl.disabled = false;
                    return;
                }

                textareaEl.value = '';
                buttonEl.disabled = false;
                await appendSentInboxMessage(data.message || {});
            } catch (error) {
                buttonEl.disabled = false;
            }
        }

        inboxConversationListEl?.addEventListener('click', (event) => {
            closeInboxMessageActionMenu();
            closeInboxConversationActionMenu();
            if (Date.now() < suppressConversationItemClickUntil) {
                event.preventDefault();
                return;
            }
            const itemEl = event.target.closest('.inbox-conversation-item');
            if (!itemEl) return;

            const partnerId = Number.parseInt(itemEl.dataset.partnerId || '', 10);
            if (!Number.isInteger(partnerId) || partnerId <= 0) return;

            activeConversationPartnerId = partnerId;
            lastReadSyncedPartnerId = 0;
            isInboxChatOpen = true;
            refreshInboxUI();
        });

        inboxConversationListEl?.addEventListener('contextmenu', (event) => {
            const itemEl = findConversationItemFromEventTarget(event.target);
            if (!itemEl) {
                closeInboxConversationActionMenu();
                return;
            }

            const partnerId = Number.parseInt(itemEl.dataset.partnerId || '', 10);
            if (!Number.isInteger(partnerId) || partnerId <= 0) {
                closeInboxConversationActionMenu();
                return;
            }

            event.preventDefault();
            openInboxConversationActionMenu(partnerId, event.clientX, event.clientY);
        });

        inboxConversationListEl?.addEventListener('touchstart', (event) => {
            const itemEl = findConversationItemFromEventTarget(event.target);
            if (!itemEl) {
                closeInboxConversationActionMenu();
                return;
            }

            if (event.touches.length !== 1) {
                clearInboxConversationLongPressTimer();
                return;
            }

            const partnerId = Number.parseInt(itemEl.dataset.partnerId || '', 10);
            if (!Number.isInteger(partnerId) || partnerId <= 0) {
                clearInboxConversationLongPressTimer();
                return;
            }

            const touchPoint = event.touches[0];
            clearInboxConversationLongPressTimer();
            longPressConversationTimer = window.setTimeout(() => {
                suppressConversationItemClickUntil = Date.now() + 700;
                openInboxConversationActionMenu(partnerId, touchPoint.clientX, touchPoint.clientY);
            }, 520);
        }, { passive: true });

        inboxConversationListEl?.addEventListener('touchend', clearInboxConversationLongPressTimer);
        inboxConversationListEl?.addEventListener('touchcancel', clearInboxConversationLongPressTimer);
        inboxConversationListEl?.addEventListener('touchmove', clearInboxConversationLongPressTimer);

        inboxMessageThreadEl?.addEventListener('contextmenu', (event) => {
            const bubbleEl = findMessageBubbleFromEventTarget(event.target);
            if (!bubbleEl) {
                closeInboxMessageActionMenu();
                return;
            }

            event.preventDefault();
            openInboxMessageActionMenu(bubbleEl.dataset.messageId || 0, bubbleEl.dataset.messageDirection || '', event.clientX, event.clientY);
        });

        inboxMessageThreadEl?.addEventListener('touchstart', (event) => {
            const bubbleEl = findMessageBubbleFromEventTarget(event.target);
            if (!bubbleEl) {
                closeInboxMessageActionMenu();
                return;
            }

            if (event.touches.length !== 1) {
                clearInboxLongPressTimer();
                return;
            }

            const touchPoint = event.touches[0];
            clearInboxLongPressTimer();
            longPressMessageTimer = window.setTimeout(() => {
                openInboxMessageActionMenu(bubbleEl.dataset.messageId || 0, bubbleEl.dataset.messageDirection || '', touchPoint.clientX, touchPoint.clientY);
            }, 520);
        }, { passive: true });

        inboxMessageThreadEl?.addEventListener('touchend', clearInboxLongPressTimer);
        inboxMessageThreadEl?.addEventListener('touchcancel', clearInboxLongPressTimer);
        inboxMessageThreadEl?.addEventListener('touchmove', clearInboxLongPressTimer);

        inboxMessageThreadEl?.addEventListener('click', async (event) => {
            const linkEl = event.target.closest('a[href]');
            if (!(linkEl instanceof HTMLAnchorElement)) return;

            const commentRef = getBulkOrderCommentRefFromInboxLink(linkEl.getAttribute('href') || '');
            if (commentRef === '') return;

            event.preventDefault();
            event.stopPropagation();

            const exists = await doesBulkOrderCommentLinkExist(commentRef);
            if (!exists) {
                showDashboardLightPopup("This comment cann't be found");
                return;
            }

            const targetHref = linkEl.href;
            const targetAttr = String(linkEl.getAttribute('target') || '').toLowerCase();
            if (targetAttr === '_blank') {
                window.open(targetHref, '_blank', 'noopener');
                return;
            }

            window.location.href = targetHref;
        }, true);

        inboxMessageDeleteBtnEl?.addEventListener('click', async () => {
            const messageId = activeMessageActionMessageId;
            closeInboxMessageActionMenu();
            if (!messageId) return;
            await deleteInboxMessageForCurrentUser(messageId);
        });

        inboxMessageReplyBtnEl?.addEventListener('click', () => {
            const messageId = activeMessageActionMessageId;
            closeInboxMessageActionMenu();
            if (!messageId) return;
            setActiveReplyMessage(messageId);
        });

        inboxMessageCopyBtnEl?.addEventListener('click', async () => {
            const messageId = activeMessageActionMessageId;
            closeInboxMessageActionMenu();
            if (!messageId) return;
            await copyInboxMessageText(messageId);
        });

        inboxConversationDeleteBtnEl?.addEventListener('click', () => {
            const partnerId = activeConversationActionPartnerId;
            closeInboxConversationActionMenu();
            if (!partnerId) return;

            pendingDeleteConversationPartnerId = partnerId;
            const modal = ensureDeleteConversationModal();
            if (modal) {
                modal.show();
                return;
            }

            showDashboardLightPopup('Please use the delete chat popup to confirm this action.');
        });

        inboxComposeReplyBoxes.forEach((boxEl) => {
            const cancelBtn = boxEl.querySelector('.inbox-compose-reply-cancel');
            cancelBtn?.addEventListener('click', clearActiveReplyMessage);
        });

        document.addEventListener('click', (event) => {
            if (!(inboxMessageActionMenuEl instanceof HTMLElement) || !inboxMessageActionMenuEl.classList.contains('active')) return;
            if (inboxMessageActionMenuEl.contains(event.target)) return;
            closeInboxMessageActionMenu();
        });

        document.addEventListener('click', (event) => {
            if (!(inboxConversationActionMenuEl instanceof HTMLElement) || !inboxConversationActionMenuEl.classList.contains('active')) return;
            if (inboxConversationActionMenuEl.contains(event.target)) return;
            closeInboxConversationActionMenu();
        });

        window.addEventListener('scroll', closeInboxMessageActionMenu, true);
        window.addEventListener('resize', closeInboxMessageActionMenu);
        window.addEventListener('scroll', closeInboxConversationActionMenu, true);
        window.addEventListener('resize', closeInboxConversationActionMenu);

        function ensureProceedViaModal() {
            if (proceedViaModal) return proceedViaModal;
            if (proceedViaModalEl && window.bootstrap && window.bootstrap.Modal) {
                proceedViaModal = new window.bootstrap.Modal(proceedViaModalEl);
            }
            return proceedViaModal;
        }

        function ensureSignOutConfirmModal() {
            if (signOutConfirmModal) return signOutConfirmModal;
            if (signOutConfirmModalEl && window.bootstrap && window.bootstrap.Modal) {
                signOutConfirmModal = new window.bootstrap.Modal(signOutConfirmModalEl);
            }
            return signOutConfirmModal;
        }

        function ensureDeleteConversationModal() {
            if (deleteConversationModal) return deleteConversationModal;
            if (deleteConversationModalEl && window.bootstrap && window.bootstrap.Modal) {
                deleteConversationModal = new window.bootstrap.Modal(deleteConversationModalEl);
            }
            return deleteConversationModal;
        }

        function showDashboardLightPopup(message) {
            if (!dashboardLightPopupEl) return;

            dashboardLightPopupEl.textContent = String(message || '');
            dashboardLightPopupEl.classList.add('active');

            if (dashboardLightPopupTimer) {
                window.clearTimeout(dashboardLightPopupTimer);
            }

            dashboardLightPopupTimer = window.setTimeout(() => {
                dashboardLightPopupEl.classList.remove('active');
            }, 2200);
        }

        function getBulkOrderCommentRefFromInboxLink(hrefValue) {
            try {
                const targetUrl = new URL(String(hrefValue || ''), window.location.origin);
                const pathname = targetUrl.pathname.toLowerCase();
                const isBulkOrdersPage = pathname.endsWith('/businessbulkorders.html') || pathname.endsWith('businessbulkorders.html');
                if (!isBulkOrdersPage) return '';

                return String(targetUrl.searchParams.get('comment_ref') || '').trim();
            } catch (error) {
                return '';
            }
        }

        async function doesBulkOrderCommentLinkExist(commentRef) {
            const safeCommentRef = String(commentRef || '').trim();
            if (safeCommentRef === '') return true;

            try {
                const targetUrl = new URL('check_bulk_order_comment.php', window.location.href);
                targetUrl.searchParams.set('comment_ref', safeCommentRef);

                const response = await fetch(targetUrl.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!response.ok) return true;

                const data = await response.json();
                return data?.ok === true && data?.exists === true;
            } catch (error) {
                return true;
            }
        }

        function setDashboardBadgeCount(badgeEl, countValue) {
            if (!(badgeEl instanceof HTMLElement)) return;
            const safeCount = Math.max(0, Number.parseInt(countValue || 0, 10) || 0);
            badgeEl.textContent = safeCount >= 100 ? '100+' : String(safeCount);
            badgeEl.classList.toggle('visible', safeCount > 0);
        }

        function refreshDashboardBadgesFromPayload(payload) {
            const purchaseCount = Number.parseInt(payload?.purchase_count || 0, 10) || 0;
            const inboxCount = Number.parseInt(payload?.inbox_count || 0, 10) || 0;
            const inboxUnreadCounts = payload?.inbox_unread_counts && typeof payload.inbox_unread_counts === 'object'
                ? payload.inbox_unread_counts
                : {};

            inboxConversations.forEach((conversation) => {
                const partnerId = Number.parseInt(conversation?.partner_id || 0, 10);
                conversation.unread_count = partnerId > 0
                    ? (Number.parseInt(inboxUnreadCounts[String(partnerId)] ?? inboxUnreadCounts[partnerId] ?? 0, 10) || 0)
                    : 0;
            });

            setDashboardBadgeCount(managePurchasesBadgeEl, purchaseCount);
            setDashboardBadgeCount(inboxTabBadgeEl, inboxCount);
            renderInboxConversationList();
        }

        async function markConversationAsChecked(partnerId) {
            const safePartnerId = Number.parseInt(partnerId || 0, 10);
            if (!Number.isInteger(safePartnerId) || safePartnerId <= 0) return;

            const conversation = getConversationByPartnerId(safePartnerId);
            if (conversation) {
                conversation.unread_count = 0;
                renderInboxConversationList();
            }

            try {
                const body = new URLSearchParams();
                body.set('partner_id', String(safePartnerId));
                body.set('csrf_token', jomuUserCsrfToken);

                const response = await fetch('mark_business_conversation_checked.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });
                if (!response.ok) return;

                const data = await response.json();
                if (!data?.success) return;

                lastReadSyncedPartnerId = safePartnerId;
                refreshDashboardBadgesFromPayload(data);
            } catch (error) {
                // Non-blocking read sync.
            }
        }

        function syncActiveConversationReadState() {
            if (activeDashboardSection !== 'inbox' || !isInboxChatOpen) return;
            if (!getConversationByPartnerId(activeConversationPartnerId)) return;
            if (lastReadSyncedPartnerId === activeConversationPartnerId) return;
            markConversationAsChecked(activeConversationPartnerId);
        }

        async function fetchDashboardBadgeCounts() {
            try {
                const response = await fetch('businessvendordashboard.php?badge_counts=1', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!response.ok) return;
                const data = await response.json();
                if (!data?.success) return;
                refreshDashboardBadgesFromPayload(data);
            } catch (error) {
                // Non-blocking badge refresh.
            }
        }

        function startDashboardBadgePolling() {
            if (dashboardBadgePollTimer) {
                clearInterval(dashboardBadgePollTimer);
            }
            fetchDashboardBadgeCounts();
            dashboardBadgePollTimer = setInterval(fetchDashboardBadgeCounts, 15000);
        }

        function buildProceedPrefillPayload() {
            if (!selectedProceedPayload) return null;

            const listingUrlObj = new URL(selectedProceedPayload.listingUrl || '/purchasewholesale.html', window.location.origin);
            // Always force read-only submitted-details view when opening from chat links.
            listingUrlObj.searchParams.set('request_view', '1');
            const listingAbsoluteUrl = listingUrlObj.toString();
            const isDeletedListing = listingUrlObj.searchParams.get('listing_not_found') === '1';
            const prefillText = isDeletedListing
                ? `Hi, regarding your ${selectedProceedPayload.requestLabel} for ${selectedProceedPayload.listingLabel}: Listing Not Found`
                : `Hi, regarding your ${selectedProceedPayload.requestLabel} for ${selectedProceedPayload.listingLabel}: ${listingAbsoluteUrl}`;

            return {
                listingAbsoluteUrl,
                prefillText
            };
        }

        async function markRequestAsProceeded(requestId, requestCardEl) {
            if (!Number.isInteger(requestId) || requestId <= 0) {
                return;
            }

            try {
                const body = new URLSearchParams();
                body.set('mark_purchase_request_proceeded_id', String(requestId));
                body.set('csrf_token', jomuUserCsrfToken);
                const response = await fetch('businessvendordashboard.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    return;
                }
                requestCardEl?.classList.remove('pending-request-highlight');
                fetchDashboardBadgeCounts();
            } catch (error) {
                // Non-blocking: Proceed flow should still continue even if this status update fails.
            }
        }

        proceedContactButtons.forEach((buttonEl) => {
            buttonEl.addEventListener('click', () => {
                const buyerId = Number.parseInt(buttonEl.dataset.buyerId || '0', 10);
                if (!Number.isInteger(buyerId) || buyerId <= 0) {
                    return;
                }
                const requestId = Number.parseInt(buttonEl.dataset.requestId || '0', 10);
                const requestCardEl = buttonEl.closest('.comment-section-bulk-orders');
                if (Number.isInteger(requestId) && requestId > 0) {
                    markRequestAsProceeded(requestId, requestCardEl);
                }

                selectedProceedPayload = {
                    buyerId,
                    buyerWhatsapp: String(buttonEl.dataset.buyerWhatsapp || ''),
                    listingUrl: String(buttonEl.dataset.listingUrl || ''),
                    listingLabel: String(buttonEl.dataset.listingLabel || 'listing'),
                    requestLabel: String(buttonEl.dataset.requestLabel || 'purchase request')
                };

                const modal = ensureProceedViaModal();
                if (modal) {
                    modal.show();
                } else {
                    // Fallback if Bootstrap modal is not ready.
                    proceedViaMessageBtn?.click();
                }
            });
        });

        signOutLinks.forEach((linkEl) => {
            linkEl.addEventListener('click', (event) => {
                event.preventDefault();
                pendingSignOutHref = String(linkEl.getAttribute('href') || '/php/auth/signout.php');
                const modal = ensureSignOutConfirmModal();
                if (modal) {
                    modal.show();
                    return;
                }
                showDashboardLightPopup('Please use the sign out popup to confirm this action.');
            });
        });

        cancelSignOutBtn?.addEventListener('click', () => {
            const modal = ensureSignOutConfirmModal();
            if (modal) modal.hide();
        });

        confirmSignOutBtn?.addEventListener('click', () => {
            window.location.href = pendingSignOutHref || '/php/auth/signout.php';
        });

        deleteConversationProceedBtnEl?.addEventListener('click', () => {
            pendingDeleteConversationPartnerId = 0;
            const modal = ensureDeleteConversationModal();
            if (modal) modal.hide();
        });

        deleteConversationConfirmBtnEl?.addEventListener('click', async () => {
            const partnerId = pendingDeleteConversationPartnerId;
            if (!Number.isInteger(partnerId) || partnerId <= 0) {
                const modal = ensureDeleteConversationModal();
                if (modal) modal.hide();
                return;
            }

            const originalLabel = deleteConversationConfirmBtnEl.textContent;
            deleteConversationConfirmBtnEl.disabled = true;
            deleteConversationConfirmBtnEl.textContent = 'Deleting...';

            try {
                await deleteInboxConversationForCurrentUser(partnerId);
            } finally {
                pendingDeleteConversationPartnerId = 0;
                deleteConversationConfirmBtnEl.disabled = false;
                deleteConversationConfirmBtnEl.textContent = originalLabel;
                const modal = ensureDeleteConversationModal();
                if (modal) modal.hide();
            }
        });

        proceedViaMessageBtn?.addEventListener('click', () => {
            if (!selectedProceedPayload) return;

            const prefillPayload = buildProceedPrefillPayload();
            if (!prefillPayload) return;
            const targetUrl = new URL('/php/businessvendordashboard.php', window.location.origin);
            targetUrl.searchParams.set('section', 'inbox');
            targetUrl.searchParams.set('partner_id', String(selectedProceedPayload.buyerId));
            targetUrl.searchParams.set('prefill', encodeURIComponent(prefillPayload.prefillText));

            const modal = ensureProceedViaModal();
            if (modal) {
                modal.hide();
            }
            window.location.href = targetUrl.toString();
        });

        proceedViaWhatsappBtn?.addEventListener('click', () => {
            if (!selectedProceedPayload) return;

            const prefillPayload = buildProceedPrefillPayload();
            if (!prefillPayload) return;
            const rawPhone = String(selectedProceedPayload.buyerWhatsapp || '').trim();
            let normalizedWhatsappNumber = rawPhone.replace(/[^\d+]/g, '');
            if (normalizedWhatsappNumber.startsWith('+')) {
                normalizedWhatsappNumber = normalizedWhatsappNumber.slice(1);
            } else if (normalizedWhatsappNumber.startsWith('00')) {
                normalizedWhatsappNumber = normalizedWhatsappNumber.slice(2);
            } else if (normalizedWhatsappNumber.startsWith('0') && normalizedWhatsappNumber.length >= 10) {
                // Assume local Uganda format (07...) and convert to international format.
                normalizedWhatsappNumber = `256${normalizedWhatsappNumber.slice(1)}`;
            }
            normalizedWhatsappNumber = normalizedWhatsappNumber.replace(/[^\d]/g, '');

            const modal = ensureProceedViaModal();
            if (modal) {
                modal.hide();
            }

            if (!normalizedWhatsappNumber) {
                showDashboardLightPopup('This business has not added a WhatsApp number yet.');
                return;
            }

            const encodedPhone = encodeURIComponent(normalizedWhatsappNumber);
            const encodedText = encodeURIComponent(prefillPayload.prefillText);
            const desktopWhatsappUrl = `https://web.whatsapp.com/send/?phone=${encodedPhone}&text=${encodedText}&type=phone_number&app_absent=0`;
            const mobileFallbackUrl = `https://wa.me/${encodedPhone}?text=${encodedText}`;
            const mobileAppUrl = `whatsapp://send?phone=${encodedPhone}&text=${encodedText}`;
            const isMobileDevice = /Android|iPhone|iPad|iPod|Windows Phone|Mobile/i.test(navigator.userAgent || '');

            if (isMobileDevice) {
                const fallbackTimer = window.setTimeout(() => {
                    window.location.href = mobileFallbackUrl;
                }, 1200);

                const clearFallback = () => {
                    window.clearTimeout(fallbackTimer);
                };

                window.addEventListener('pagehide', clearFallback, { once: true });
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        clearFallback();
                    }
                }, { once: true });

                window.location.href = mobileAppUrl;
                return;
            }

            window.location.href = desktopWhatsappUrl;
        });

        mainNavChatBackEl?.addEventListener('click', () => {
            isInboxChatOpen = false;
            lastReadSyncedPartnerId = 0;
            refreshInboxUI();
        });

        inboxSendButtons.forEach((buttonEl) => {
            buttonEl.addEventListener('click', async () => {
                const parentArea = buttonEl.closest('.message-area-div');
                const textareaEl = parentArea?.querySelector('.inbox-message-input');
                if (!textareaEl || !activeConversationPartnerId) {
                    return;
                }

                const textValue = textareaEl.value.trim();
                if (textValue !== '') {
                    await sendInboxTextMessage(textValue, textareaEl, buttonEl);
                }
            });
        });

        if (dashboardInitialSection === 'inbox') {
            showDashboardSection('inbox', { openConversation: requestedInboxPartnerId > 0 });
        } else if (dashboardInitialSection === 'purchases') {
            showDashboardSection('purchases');
        } else {
            showDashboardSection('listings');
        }
        startDashboardBadgePolling();
        window.addEventListener('beforeunload', () => {
            if (dashboardBadgePollTimer) {
                clearInterval(dashboardBadgePollTimer);
                dashboardBadgePollTimer = null;
            }
        });

        // Keep only one user-controlled video playing at a time.
        document.addEventListener("play", function (event) {
            const currentVideo = event.target;
            if (!(currentVideo instanceof HTMLVideoElement) || !currentVideo.hasAttribute("controls")) {
                return;
            }

            document.querySelectorAll("video[controls]").forEach(function (video) {
                if (video !== currentVideo && !video.paused) {
                    video.pause();
                }
            });
        }, true);

        const dashboardMediaPreviewOverlay = document.getElementById('dashboardMediaPreviewOverlay');
        const dashboardMediaPreviewClose = document.getElementById('dashboardMediaPreviewClose');
        const dashboardMediaPreviewImage = document.getElementById('dashboardMediaPreviewImage');
        const dashboardMediaPreviewVideo = document.getElementById('dashboardMediaPreviewVideo');
        const dashboardMediaPreviewDetails = document.getElementById('dashboardMediaPreviewDetails');
        const dashboardMediaPreviewTitle = document.getElementById('dashboardMediaPreviewTitle');
        const dashboardMediaPreviewPrice = document.getElementById('dashboardMediaPreviewPrice');
        const dashboardMediaPreviewDescription = document.getElementById('dashboardMediaPreviewDescription');

        if (dashboardMediaPreviewOverlay && dashboardMediaPreviewOverlay.parentElement !== document.body) {
            document.body.appendChild(dashboardMediaPreviewOverlay);
        }
        const countedDashboardPreviewViews = new Set();
        const countedDashboardVideoViews = new Set();
        const pendingDashboardVideoViewTimers = new Map();

        function updateListingViewLabels(listingId, label) {
            if (!Number.isInteger(listingId) || listingId <= 0 || !label) return;
            document.querySelectorAll(`[data-listing-view-label="${listingId}"]`).forEach((labelEl) => {
                labelEl.textContent = label;
            });
        }

        async function incrementPreviewImageView(sourceEl) {
            const type = String(sourceEl?.dataset.previewType || '').trim();
            const listingId = Number.parseInt(sourceEl?.dataset.previewListingId || '', 10);
            if (type !== 'image' || !Number.isInteger(listingId) || listingId <= 0 || countedDashboardPreviewViews.has(listingId)) {
                return;
            }

            countedDashboardPreviewViews.add(listingId);

            try {
                const response = await fetch(`increment_listing_view.php?listing_id=${encodeURIComponent(String(listingId))}`);
                if (!response.ok) return;
                const data = await response.json();
                if (data?.success && typeof data.label === 'string') {
                    updateListingViewLabels(listingId, data.label);
                }
            } catch (error) {
                // Non-blocking analytics update.
            }
        }

        async function incrementVideoPlaybackView(listingId) {
            if (!Number.isInteger(listingId) || listingId <= 0 || countedDashboardVideoViews.has(listingId)) {
                return;
            }

            countedDashboardVideoViews.add(listingId);

            try {
                const response = await fetch(`increment_listing_view.php?listing_id=${encodeURIComponent(String(listingId))}`);
                if (!response.ok) return;
                const data = await response.json();
                if (data?.success && typeof data.label === 'string') {
                    updateListingViewLabels(listingId, data.label);
                }
            } catch (error) {
                // Non-blocking analytics update.
            }
        }

        function clearPendingVideoView(videoEl) {
            const timerId = pendingDashboardVideoViewTimers.get(videoEl);
            if (timerId) {
                clearTimeout(timerId);
                pendingDashboardVideoViewTimers.delete(videoEl);
            }
        }

        function scheduleVideoViewIncrement(videoEl) {
            const listingId = Number.parseInt(videoEl?.dataset.previewListingId || '', 10);
            if (!Number.isInteger(listingId) || listingId <= 0 || countedDashboardVideoViews.has(listingId) || pendingDashboardVideoViewTimers.has(videoEl)) {
                return;
            }

            const timerId = setTimeout(() => {
                pendingDashboardVideoViewTimers.delete(videoEl);
                if (countedDashboardVideoViews.has(listingId) || videoEl.paused || videoEl.ended) {
                    return;
                }
                incrementVideoPlaybackView(listingId);
            }, 2000);

            pendingDashboardVideoViewTimers.set(videoEl, timerId);
        }

        function registerVideoViewTracking(videoEl) {
            if (!(videoEl instanceof HTMLVideoElement) || videoEl.dataset.viewTrackingBound === '1') {
                return;
            }

            videoEl.dataset.viewTrackingBound = '1';
            videoEl.addEventListener('play', () => scheduleVideoViewIncrement(videoEl));
            videoEl.addEventListener('pause', () => clearPendingVideoView(videoEl));
            videoEl.addEventListener('ended', () => clearPendingVideoView(videoEl));
            videoEl.addEventListener('emptied', () => clearPendingVideoView(videoEl));
        }

        function updateDashboardPreviewDetails(sourceEl) {
            if (!dashboardMediaPreviewDetails) return;
            const title = String(sourceEl?.dataset.previewTitle || '').trim();
            const price = String(sourceEl?.dataset.previewPrice || '').trim();
            const description = String(sourceEl?.dataset.previewDescription || '');
            const hasDetails = title !== '' || price !== '' || description !== '';

            dashboardMediaPreviewTitle.textContent = title;
            dashboardMediaPreviewTitle.style.display = title ? 'block' : 'none';
            dashboardMediaPreviewPrice.textContent = price ? `Price: ${price}` : '';
            dashboardMediaPreviewPrice.style.display = price ? 'block' : 'none';
            dashboardMediaPreviewDescription.textContent = description;
            dashboardMediaPreviewDescription.style.whiteSpace = 'pre-wrap';
            dashboardMediaPreviewDescription.style.wordBreak = 'break-word';
            dashboardMediaPreviewDescription.style.display = description ? 'block' : 'none';
            dashboardMediaPreviewDetails.style.display = hasDetails ? 'block' : 'none';
        }

        function closeDashboardMediaPreview() {
            dashboardMediaPreviewOverlay.classList.remove('active');
            dashboardMediaPreviewOverlay.setAttribute('aria-hidden', 'true');
            dashboardMediaPreviewVideo.pause();
            dashboardMediaPreviewVideo.removeAttribute('src');
            delete dashboardMediaPreviewVideo.dataset.previewListingId;
            dashboardMediaPreviewImage.removeAttribute('src');
            dashboardMediaPreviewImage.style.display = 'none';
            dashboardMediaPreviewVideo.style.display = 'none';
            if (dashboardMediaPreviewDetails) {
                dashboardMediaPreviewDetails.style.display = 'none';
            }
        }

        function openDashboardMediaPreview(type, src, sourceEl) {
            if (!src) return;
            dashboardMediaPreviewImage.style.display = 'none';
            dashboardMediaPreviewVideo.style.display = 'none';
            updateDashboardPreviewDetails(sourceEl);
            incrementPreviewImageView(sourceEl);

            if (type === 'video') {
                dashboardMediaPreviewImage.removeAttribute('src');
                dashboardMediaPreviewVideo.src = src;
                dashboardMediaPreviewVideo.dataset.previewListingId = sourceEl?.dataset.previewListingId || '';
                dashboardMediaPreviewVideo.style.display = 'block';
                dashboardMediaPreviewVideo.play().catch(() => {});
            } else {
                dashboardMediaPreviewVideo.pause();
                dashboardMediaPreviewVideo.removeAttribute('src');
                dashboardMediaPreviewImage.src = src;
                dashboardMediaPreviewImage.style.display = 'block';
            }

            dashboardMediaPreviewOverlay.classList.add('active');
            dashboardMediaPreviewOverlay.setAttribute('aria-hidden', 'false');
        }

        function openDashboardPreviewFromSource(sourceEl) {
            if (!sourceEl) return;
            const type = sourceEl.dataset.previewType || (sourceEl.tagName.toLowerCase() === 'video' ? 'video' : 'image');
            const src = sourceEl.dataset.previewSrc || sourceEl.getAttribute('src') || '';
            openDashboardMediaPreview(type, src, sourceEl);
        }

        function getEventTargetElement(event) {
            const rawTarget = event?.target;
            if (rawTarget instanceof Element) {
                return rawTarget;
            }
            return rawTarget && rawTarget.parentElement instanceof Element ? rawTarget.parentElement : null;
        }

        const dashboardListingsEl = document.querySelector('#dashboard-listings');
        const dashboardPurchasesEl = document.querySelector('#dashboard-purchases');
        let lastDashboardTapTime = 0;
        let lastDashboardTapSrc = '';

        dashboardListingsEl?.addEventListener('click', (event) => {
            if (event.target.closest('.add-listing-card')) {
                return;
            }
            const sourceEl = event.target.closest('.media-preview-source') || event.target.closest('.video-stock-title, .video-description-brief, .video-hashtags, .listing-name-top, .listing-description, .listing-description-link')?.closest('.card')?.querySelector('.media-preview-source');
            openDashboardPreviewFromSource(sourceEl);
        });

        dashboardListingsEl?.addEventListener('touchend', (event) => {
            if (event.target.closest('.add-listing-card')) {
                return;
            }
            const sourceEl = event.target.closest('.media-preview-source') || event.target.closest('.video-stock-title, .video-description-brief, .video-hashtags, .listing-name-top, .listing-description, .listing-description-link')?.closest('.card')?.querySelector('.media-preview-source');
            if (!sourceEl) return;
            const sourceKey = sourceEl.dataset.previewSrc || sourceEl.getAttribute('src') || '';
            const now = Date.now();
            const isDoubleTap = now - lastDashboardTapTime < 350 && sourceKey !== '' && sourceKey === lastDashboardTapSrc;

            lastDashboardTapTime = now;
            lastDashboardTapSrc = sourceKey;

            if (!isDoubleTap) return;
            event.preventDefault();
            openDashboardPreviewFromSource(sourceEl);
            lastDashboardTapTime = 0;
            lastDashboardTapSrc = '';
        }, { passive: false });

        dashboardPurchasesEl?.addEventListener('click', (event) => {
            const targetEl = getEventTargetElement(event);
            const sourceEl = targetEl?.closest('.media-preview-source')
                || targetEl?.closest('.purchase-preview-trigger')?.closest('.card')?.querySelector('.media-preview-source');
            if (sourceEl) {
                openDashboardPreviewFromSource(sourceEl);
                return;
            }

            const purchaseCard = targetEl?.closest('.purchase-listing-card');
            if (purchaseCard?.dataset.listingDeleted === '1') {
                // Do nothing for deleted listings
            }
        });

        dashboardPurchasesEl?.addEventListener('touchend', (event) => {
            const targetEl = getEventTargetElement(event);
            const sourceEl = targetEl?.closest('.media-preview-source')
                || targetEl?.closest('.purchase-preview-trigger')?.closest('.card')?.querySelector('.media-preview-source');
            if (!sourceEl) {
                const purchaseCard = targetEl?.closest('.purchase-listing-card');
                if (purchaseCard?.dataset.listingDeleted === '1') {
                    event.preventDefault();
                    // Do nothing for deleted listings
                }
                return;
            }
            event.preventDefault();
            openDashboardPreviewFromSource(sourceEl);
        }, { passive: false });

        document.addEventListener('touchstart', () => {
            if (Date.now() - lastDashboardTapTime > 600) {
                lastDashboardTapTime = 0;
                lastDashboardTapSrc = '';
            }
        });

        document.querySelectorAll('video[data-preview-listing-id]').forEach((videoEl) => {
            registerVideoViewTracking(videoEl);
        });
        registerVideoViewTracking(dashboardMediaPreviewVideo);

        dashboardMediaPreviewClose?.addEventListener('click', closeDashboardMediaPreview);
        dashboardMediaPreviewOverlay?.addEventListener('click', (event) => {
            if (event.target === dashboardMediaPreviewOverlay) {
                closeDashboardMediaPreview();
            }
        });

        const deleteListingModalEl = document.getElementById('deleteListingModal');
        const proceedDeleteBtn = document.getElementById('proceed-delete-btn');
        const declineDeleteBtn = document.getElementById('decline-delete-btn');
        const declineRequestModalEl = document.getElementById('declineRequestModal');
        const declineRequestModalTitleEl = document.getElementById('decline-request-modal-title');
        const declineRequestModalMessageEl = document.getElementById('decline-request-modal-message');
        const declineRequestYesBtn = document.getElementById('decline-request-yes-btn');
        const declineRequestNoBtn = document.getElementById('decline-request-no-btn');
        let deleteListingModal = null;
        let pendingDeleteListingId = null;
        let pendingDeleteCard = null;
        let declineRequestModal = null;
        let pendingDeclineRequestId = null;
        let pendingDeclineRequestCard = null;

        function ensureDeleteListingModal() {
            if (deleteListingModal) return deleteListingModal;
            if (deleteListingModalEl && window.bootstrap && window.bootstrap.Modal) {
                deleteListingModal = new window.bootstrap.Modal(deleteListingModalEl);
            }
            return deleteListingModal;
        }

        function ensureDeclineRequestModal() {
            if (declineRequestModal) return declineRequestModal;
            if (declineRequestModalEl && window.bootstrap && window.bootstrap.Modal) {
                declineRequestModal = new window.bootstrap.Modal(declineRequestModalEl);
            }
            return declineRequestModal;
        }

        function closeDashboardListingDropdowns(exceptDropdown = null) {
            document.querySelectorAll('#dashboard-listings .card-options.dropdown.is-open').forEach((dropdown) => {
                if (dropdown === exceptDropdown) {
                    return;
                }

                dropdown.classList.remove('is-open');
                dropdown.querySelector('.manage-listing-options-trigger')?.setAttribute('aria-expanded', 'false');
            });
        }

        document.querySelectorAll('#dashboard-listings .manage-listing-options-trigger').forEach((trigger) => {
            const toggleDropdown = (event) => {
                event.preventDefault();
                event.stopPropagation();

                const dropdown = trigger.closest('.card-options.dropdown');
                if (!dropdown) {
                    return;
                }

                const willOpen = !dropdown.classList.contains('is-open');
                closeDashboardListingDropdowns(dropdown);
                dropdown.classList.toggle('is-open', willOpen);
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            };

            trigger.addEventListener('click', toggleDropdown);
            trigger.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                toggleDropdown(event);
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('#dashboard-listings .card-options.dropdown')) {
                return;
            }
            closeDashboardListingDropdowns();
        });

        function setDeclineRequestModalCopy(titleText) {
            const isSchedule = String(titleText || '').toLowerCase().includes('schedule');
            if (declineRequestModalTitleEl) {
                declineRequestModalTitleEl.innerHTML = `<strong>${isSchedule ? 'Decline Schedule' : 'Decline Purchase'}</strong>`;
            }
            if (declineRequestModalMessageEl) {
                declineRequestModalMessageEl.textContent = isSchedule
                    ? 'Declined schedule will be deleted.'
                    : 'Declined purchase will be deleted.';
            }
        }

        document.querySelectorAll('.manage-listing-share').forEach((shareLink) => {
            shareLink.addEventListener('click', async (event) => {
                event.preventDefault();
                const relativeUrl = shareLink.dataset.shareUrl || '';
                if (!relativeUrl) return;
                const absoluteUrl = new URL(relativeUrl, window.location.origin).toString();

                if (navigator.share) {
                    try {
                        await navigator.share({ url: absoluteUrl });
                        return;
                    } catch (error) {
                        // Fall back to clipboard if share is canceled/unavailable.
                    }
                }

                try {
                    await navigator.clipboard.writeText(absoluteUrl);
                    showDashboardLightPopup('Listing link copied.');
                } catch (error) {
                    showDashboardLightPopup(`Copy this listing link: ${absoluteUrl}`);
                }
            });
        });

        document.querySelectorAll('.manage-listing-delete').forEach((deleteLink) => {
            deleteLink.addEventListener('click', (event) => {
                event.preventDefault();
                pendingDeleteListingId = parseInt(deleteLink.dataset.listingId || '', 10);
                pendingDeleteCard = deleteLink.closest('.col-4');
                if (!Number.isInteger(pendingDeleteListingId) || pendingDeleteListingId <= 0) {
                    pendingDeleteListingId = null;
                    pendingDeleteCard = null;
                    return;
                }
                const modal = ensureDeleteListingModal();
                if (modal) {
                    modal.show();
                    return;
                }

                showDashboardLightPopup('Please use the delete listing popup to confirm this action.');
            });
        });

        declinePurchaseButtons.forEach((buttonEl) => {
            buttonEl.addEventListener('click', () => {
                const requestId = Number.parseInt(buttonEl.dataset.requestId || '0', 10);
                if (!Number.isInteger(requestId) || requestId <= 0) {
                    return;
                }

                pendingDeclineRequestId = requestId;
                pendingDeclineRequestCard = buttonEl.closest('.comment-section-bulk-orders');
                setDeclineRequestModalCopy(String(buttonEl.dataset.declineTitle || 'Decline Purchase'));

                const modal = ensureDeclineRequestModal();
                if (modal) {
                    modal.show();
                    return;
                }

                const fallbackMessage = declineRequestModalMessageEl?.textContent || 'Declined purchase will be deleted.';
                showDashboardLightPopup(fallbackMessage);
            });
        });

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.mark-out-of-stock-btn');
            if (!button) return;
            if (button.dataset.listingDeleted === '1') return;

            event.preventDefault();

            const listingId = parseInt(button.dataset.listingId || '', 10);
            const card = button.closest('.card');
            if (!Number.isInteger(listingId) || listingId <= 0 || !card) return;

            button.disabled = true;
            const originalLabel = button.textContent;
            button.textContent = 'Marking...';

            try {
                const body = new URLSearchParams();
                body.set('mark_out_of_stock_listing_id', String(listingId));

                const response = await fetch('toggle_out_of_stock.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({ listing_id: String(listingId), csrf_token: jomuUserCsrfToken }).toString()
                });
                const data = await response.json();

                if (!response.ok || !data.success) {
                    button.disabled = false;
                    button.textContent = originalLabel;
                    return;
                }

                const isOutOfStock = !!data.out_of_stock;
                card.classList.toggle('owner-out-of-stock-card', isOutOfStock);
                button.dataset.outOfStock = isOutOfStock ? '1' : '0';
                button.textContent = data.button_label || (isOutOfStock ? 'Out of Stock.' : 'Mark as out of stock');
                button.disabled = false;
            } catch (error) {
                button.disabled = false;
                button.textContent = originalLabel;
            }
        });

        if (declineDeleteBtn) {
            declineDeleteBtn.addEventListener('click', () => {
                pendingDeleteListingId = null;
                pendingDeleteCard = null;
                const modal = ensureDeleteListingModal();
                if (modal) modal.hide();
            });
        }

        declineRequestNoBtn?.addEventListener('click', () => {
            pendingDeclineRequestId = null;
            pendingDeclineRequestCard = null;
            const modal = ensureDeclineRequestModal();
            if (modal) modal.hide();
        });

        if (proceedDeleteBtn) {
            proceedDeleteBtn.addEventListener('click', async () => {
                if (!Number.isInteger(pendingDeleteListingId) || pendingDeleteListingId <= 0) {
                    const modal = ensureDeleteListingModal();
                    if (modal) modal.hide();
                    return;
                }

                try {
                    const body = new URLSearchParams();
                    body.set('listing_id', String(pendingDeleteListingId));
                    body.set('csrf_token', jomuUserCsrfToken);
                    const response = await fetch('delete_listing.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: body.toString()
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        showDashboardLightPopup(data.message || 'Unable to delete listing.');
                        return;
                    }

                    if (pendingDeleteCard) {
                        pendingDeleteCard.remove();
                    } else {
                        window.location.reload();
                    }
                } catch (error) {
                    showDashboardLightPopup('Network error while deleting listing.');
                } finally {
                    pendingDeleteListingId = null;
                    pendingDeleteCard = null;
                    const modal = ensureDeleteListingModal();
                    if (modal) modal.hide();
                }
            });
        }

        declineRequestYesBtn?.addEventListener('click', async () => {
            if (!Number.isInteger(pendingDeclineRequestId) || pendingDeclineRequestId <= 0) {
                const modal = ensureDeclineRequestModal();
                if (modal) modal.hide();
                return;
            }

            const originalLabel = declineRequestYesBtn.textContent;
            declineRequestYesBtn.disabled = true;
            declineRequestYesBtn.textContent = 'Declining...';

            try {
                const body = new URLSearchParams();
                body.set('decline_purchase_request_id', String(pendingDeclineRequestId));
                body.set('csrf_token', jomuUserCsrfToken);
                const response = await fetch('businessvendordashboard.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    showDashboardLightPopup('Unable to decline this request right now.');
                    return;
                }

                if (pendingDeclineRequestCard) {
                    const requestsGroup = pendingDeclineRequestCard.closest('.listing-requests-group');
                    pendingDeclineRequestCard.remove();

                    if (requestsGroup && !requestsGroup.querySelector('.comment-section-bulk-orders')) {
                        const listingBlock = requestsGroup.closest('.purchases-container');
                        listingBlock?.remove();
                    }

                    if (!document.querySelector('#dashboard-purchases .purchases-container')) {
                        const existingEmptyState = document.getElementById('purchase-empty-state');
                        if (!existingEmptyState) {
                            const purchasesContainerEl = document.querySelector('#dashboard-purchases .container');
                            if (purchasesContainerEl) {
                                const emptyStateEl = document.createElement('h6');
                                emptyStateEl.className = 'mt-3';
                                emptyStateEl.id = 'purchase-empty-state';
                                emptyStateEl.textContent = 'No purchase or schedule requests yet.';
                                purchasesContainerEl.appendChild(emptyStateEl);
                            }
                        }
                    }
                } else {
                    window.location.reload();
                }
                fetchDashboardBadgeCounts();
            } catch (error) {
                showDashboardLightPopup('Network error while declining request.');
            } finally {
                pendingDeclineRequestId = null;
                pendingDeclineRequestCard = null;
                declineRequestYesBtn.disabled = false;
                declineRequestYesBtn.textContent = originalLabel;
                const modal = ensureDeclineRequestModal();
                if (modal) modal.hide();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && dashboardMediaPreviewOverlay.classList.contains('active')) {
                closeDashboardMediaPreview();
            }
        });
    </script>
    <script src="/assets/listing-preview-modal.js"></script>
    <script>
        document.querySelectorAll('.add-listing-card .media-preview-source').forEach((imageEl) => {
            imageEl.classList.remove('media-preview-source');
            delete imageEl.dataset.previewType;
            delete imageEl.dataset.previewSrc;
            delete imageEl.dataset.previewTitle;
            delete imageEl.dataset.previewPrice;
            delete imageEl.dataset.previewDescription;
        });
    </script>
    <script src="/assets/listing-preview-gallery.js"></script>
    <script src="/assets/bootstrap.bundle.min.js"></script>
    <script src="../assets/cookie-consent.js"></script>
</body>

</html>

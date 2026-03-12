<?php

return [
    // Layout
    'live' => 'Live',
    'disconnected' => 'Disconnected',
    'updated' => 'Updated',

    // Summary cards
    'sessions' => 'Sessions',
    'active' => 'active',
    'active_time' => 'Active time',
    'tokens_metrics' => 'Tokens (metrics)',
    'in' => 'In',
    'out' => 'Out',
    'api_requests_events' => 'API Requests (events)',
    'errors' => 'errors',
    'code_git' => 'Code & Git',
    'commits' => 'Commits',
    'prs' => 'PRs',

    // Billing-aware cost labels
    'cost_label_subscription' => 'Usage Value (USD)',
    'cost_label_api' => 'Est. Cost (USD)',
    'cost_tooltip_subscription' => 'Calculated token usage value — included in your subscription',
    'cost_tooltip_api' => 'Estimated charges based on token usage × model pricing',
    'cost_sub_subscription' => 'included in subscription',
    'cost_sub_api' => 'estimated charges',
    'cost_col_subscription' => 'Value',
    'cost_col_api' => 'Est. Cost',
    'cost_table_subscription' => 'Usage Value by Model',
    'cost_table_api' => 'Est. Cost by Model',
    'cost_field_subscription' => 'Usage Value',
    'cost_field_api' => 'Est. Cost',

    // Cost by model table
    'no_api_data' => 'No API request data yet.',
    'model' => 'Model',
    'reqs' => 'Reqs',
    'cache_r' => 'Cache R',
    'cache_w' => 'Cache W',

    // Token breakdown
    'token_breakdown' => 'Token Breakdown',
    'input' => 'Input',
    'output' => 'Output',
    'cache_read' => 'Cache Read',
    'cache_creation' => 'Cache Creation',

    // Tool usage
    'tool_usage' => 'Tool Usage',
    'no_tool_data' => 'No tool data yet.',
    'tool' => 'Tool',
    'calls' => 'Calls',
    'success' => 'Success',
    'avg_ms' => 'Avg ms',

    // API performance
    'api_performance' => 'API Performance',
    'total_requests' => 'Total Requests',
    'avg_response' => 'Avg Response',
    'error_rate' => 'Error Rate',

    // Sessions table
    'session_id' => 'Session ID',
    'project' => 'Project',
    'email' => 'Email',
    'terminal' => 'Terminal',
    'version' => 'Version',
    'last_seen' => 'Last Seen',
    'actions' => 'Actions',
    'delete' => 'delete',
    'reset_all_data' => 'Reset All Data',
    'reset_confirm' => 'Reset ALL telemetry data? This cannot be undone.',
    'delete_session_confirm' => 'Delete session :id?',
    'no_sessions' => 'No sessions recorded yet.',
    'working' => 'Working',
    'idle' => 'Idle',
    'inactive' => 'Inactive',

    // Recent events
    'recent_events' => 'Recent Events',
    'no_events' => 'No events recorded yet.',
    'time' => 'Time',
    'event' => 'Event',
    'session' => 'Session',
    'details' => 'Details',

    // Session detail
    'back_to_dashboard' => 'Back to Dashboard',
    'merge_into' => 'Merge into',
    'no_project' => 'no project',
    'merge' => 'Merge',
    'merge_confirm' => 'Merge this session into the selected one? This session will be deleted.',
    'delete_session' => 'Delete Session',
    'delete_session_detail_confirm' => 'Delete this session and all its metrics/events?',

    // Activity status
    'activity_status' => 'Activity Status',
    'loading' => 'Loading...',
    'current_activity' => 'Current Activity',
    'last_activity' => 'Last Activity',
    'events_5min' => 'Events (5min)',
    'rate_events_min' => 'Rate (events/min)',
    'activity_trend' => 'Activity Trend (last 5 min)',
    'recent_activity' => 'Recent Activity',
    'no_data_yet' => 'No data yet',
    'no_events_yet' => 'No events yet',

    // Session info fields
    'user_id' => 'User ID',
    'first_seen' => 'First Seen',
    'tokens' => 'Tokens',

    // Metrics table
    'metrics' => 'Metrics',
    'metric' => 'Metric',
    'value' => 'Value',
    'unit' => 'Unit',
    'attributes' => 'Attributes',

    // Relative time (JS)
    'seconds_ago' => 'seconds ago',
    'minutes_ago' => 'minutes ago',
    'hours_ago' => 'hours ago',
    'days_ago' => 'days ago',

    // Events table
    'events' => 'Events',

    // CLI
    'cli_title' => 'CLAUDE BOARD ⟦ DASHBOARD ⟧',
    'cli_sessions_total' => 'Sessions (total)',
    'cli_sessions_active' => 'Sessions (active)',
    'cli_total_tokens' => 'Total Tokens',
    'cli_active_time' => 'Active Time',
    'cli_lines_added' => 'Lines Added',
    'cli_lines_removed' => 'Lines Removed',
    'cli_pull_requests' => 'Pull Requests',
    'cli_token_breakdown' => 'TOKEN BREAKDOWN',
    'cli_type' => 'Type',
    'cli_count' => 'Count',
    'cli_cost_table_subscription' => 'USAGE VALUE BY MODEL',
    'cli_cost_table_api' => 'EST. COST BY MODEL',
    'cli_cost_col_subscription' => 'Value (USD)',
    'cli_cost_col_api' => 'Est. Cost (USD)',
    'cli_tool_usage' => 'TOOL USAGE',
    'cli_invocations' => 'Invocations',
    'cli_success_rate' => 'Success Rate',
    'cli_avg_duration' => 'Avg Duration',
    'cli_api_performance' => 'API PERFORMANCE',
    'cli_total_errors' => 'Total Errors',
    'cli_avg_response_time' => 'Avg Response Time',
    'cli_recent_events' => 'RECENT EVENTS',
    'cli_watch_mode' => 'Watch mode active — refreshing every 5 seconds. Press Ctrl+C to stop.',
    'cli_session_not_found' => 'Session not found: :id',
    'cli_delete_confirm' => 'Delete session :id and all its metrics/events?',
    'cli_aborted' => 'Aborted.',
    'cli_session_deleted' => 'Session :id deleted.',
    'cli_merge_format' => 'Format: --merge=SOURCE_ID:TARGET_ID',
    'cli_merge_same' => 'Source and target session cannot be the same.',
    'cli_source_not_found' => 'Source session not found: :id',
    'cli_target_not_found' => 'Target session not found: :id',
    'cli_merge_confirm' => 'Merge :source (:metrics metrics, :events events) into :target? Source session will be deleted.',
    'cli_session_merged' => 'Session :source merged into :target.',
    'cli_no_data' => 'No data to reset.',
    'cli_reset_confirm' => 'Reset ALL telemetry data? (:count sessions will be deleted)',
    'cli_reset_done' => 'All telemetry data has been reset.',
    'cli_field' => 'Field',
];

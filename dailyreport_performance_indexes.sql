-- Daily Report Performance Indexes
-- Run this on your production/server database.
-- Note: if an index already exists with same name, MySQL will throw duplicate error; skip that line.

-- Core master filtering
ALTER TABLE dailyreport_master
  ADD INDEX idx_dm_reportdate_user_branch_type (report_date, user_id, branch_id, report_type),
  ADD INDEX idx_dm_user_branch_date (user_id, branch_id, report_date),
  ADD INDEX idx_dm_status (status);

-- Followup aggregate used by view/export
ALTER TABLE enquiry_followups
  ADD INDEX idx_ef_date_user_branch (followup_date, created_by, branch_id);

-- Activity joins
ALTER TABLE dailyreport_frontoffice_activity
  ADD INDEX idx_drfo_activity_master (master_id);
ALTER TABLE dailyreport_hr_activity
  ADD INDEX idx_drhr_activity_master (master_id);
ALTER TABLE dailyreport_marketing_activity
  ADD INDEX idx_drmk_activity_master (master_id);

-- Front Office detail tables
ALTER TABLE dailyreport_frontoffice_registration_rows
  ADD INDEX idx_drfo_reg_master (master_id);
ALTER TABLE dailyreport_frontoffice_planner_rows
  ADD INDEX idx_drfo_planner_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_frontoffice_hourly_rows
  ADD INDEX idx_drfo_hourly_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_frontoffice_college_followup_rows
  ADD INDEX idx_drfo_college_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_frontoffice_college_followup_status
  ADD INDEX idx_drfo_college_status_row (followup_row_id);
ALTER TABLE dailyreport_frontoffice_database_followup_rows
  ADD INDEX idx_drfo_db_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_frontoffice_database_followup_status
  ADD INDEX idx_drfo_db_status_row (database_row_id);

-- HR detail tables
ALTER TABLE dailyreport_hr_hourly_rows
  ADD INDEX idx_drhr_hourly_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_hr_internship_rows
  ADD INDEX idx_drhr_intern_master (master_id);
ALTER TABLE dailyreport_hr_interview_rows
  ADD INDEX idx_drhr_interview_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_hr_placement_call_rows
  ADD INDEX idx_drhr_placement_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_hr_old_client_rows
  ADD INDEX idx_drhr_old_master (master_id);
ALTER TABLE dailyreport_hr_new_client_rows
  ADD INDEX idx_drhr_new_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_hr_college_data_rows
  ADD INDEX idx_drhr_cd_master (master_id);
ALTER TABLE dailyreport_hr_college_followup_rows
  ADD INDEX idx_drhr_cf_master_sort (master_id, sort_order);

-- Marketing detail tables
ALTER TABLE dailyreport_marketing_hourly_rows
  ADD INDEX idx_drmk_hourly_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_marketing_colleges_rows
  ADD INDEX idx_drmk_college_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_marketing_prospect_rows
  ADD INDEX idx_drmk_prospect_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_marketing_prospect_status_rows
  ADD INDEX idx_drmk_prospect_status_row_sort (prospect_row_id, sort_order);
ALTER TABLE dailyreport_marketing_act_report_rows
  ADD INDEX idx_drmk_act_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_marketing_amount_rows
  ADD INDEX idx_drmk_amount_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_marketing_program_rows
  ADD INDEX idx_drmk_program_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_marketing_arts_college_rows
  ADD INDEX idx_drmk_arts_college_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_marketing_arts_pc_rows
  ADD INDEX idx_drmk_arts_pc_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_marketing_engg_college_rows
  ADD INDEX idx_drmk_engg_college_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_marketing_engg_pc_rows
  ADD INDEX idx_drmk_engg_pc_master_sort (master_id, sort_order);
ALTER TABLE dailyreport_marketing_polytech_college_rows
  ADD INDEX idx_drmk_polytech_master_sort (master_id, sort_order);

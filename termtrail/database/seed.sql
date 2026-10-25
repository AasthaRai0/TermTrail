-- TermTrail AI demo data (entirely fictional). OPTIONAL.
-- Safe to run more than once and safe on a database that already has data: it only adds the demo records
-- if they are not there yet and never updates or deletes anything that exists.
--
-- DEMO LOGINS  (change or deactivate these before any real deployment!)
--   Admin  : demo@termtrail.test    / Demo@12345
--   Viewer : viewer@termtrail.test  / Demo@12345
--
-- Dates are relative to the day you import, so the dashboard, calendar and reminders always look current.
-- The demo contracts have no uploaded files; upload a PDF/DOCX/TXT to try extraction, analysis and versions.
SET NAMES utf8mb4;

INSERT INTO users (name,email,password_hash,role)
  SELECT 'Demo Admin','demo@termtrail.test','$2y$10$BtDNJkE0JWtGzGOgNZLRL.OBH23aDrKLOaS3t2qTTeWfhcCWsWsS2','admin' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='demo@termtrail.test');
INSERT INTO users (name,email,password_hash,role)
  SELECT 'Demo Viewer','viewer@termtrail.test','$2y$10$BtDNJkE0JWtGzGOgNZLRL.OBH23aDrKLOaS3t2qTTeWfhcCWsWsS2','viewer' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='viewer@termtrail.test');
SET @a := (SELECT id FROM users WHERE email='demo@termtrail.test');
SET @v := (SELECT id FROM users WHERE email='viewer@termtrail.test');
SET @fresh := NOT EXISTS (SELECT 1 FROM contracts WHERE title='Orbit Managed Services Agreement' AND owner_id=@a);

-- Counterparties -----------------------------------------------------------------------------
INSERT INTO counterparties (owner_id,name,contact_person,email,phone,address,notes)
  SELECT @a,'Orbit Software LLP','Meera Nair','legal@orbit-software.example','+91 98765 43210','12 Tech Park Road, Pune, India','Primary managed-services vendor.' FROM DUAL WHERE @fresh;
INSERT INTO counterparties (owner_id,name,contact_person,email,phone,address,notes)
  SELECT @a,'Acme Retail Pvt Ltd','Rohan Mehta','contracts@acme-retail.example','+91 91234 56789','88 Market Street, Mumbai, India','Long-term supplier.' FROM DUAL WHERE @fresh;
INSERT INTO counterparties (owner_id,name,contact_person,email,phone,address,notes)
  SELECT @a,'BlueWave Logistics','Ana Silva','ana@bluewave-logistics.example','+44 20 7946 0000','1 Dock Lane, Liverpool, UK',NULL FROM DUAL WHERE @fresh;
INSERT INTO counterparties (owner_id,name,contact_person,email,phone,address,notes)
  SELECT @a,'Nimbus Cloud Services','Sam Patel','sales@nimbus-cloud.example','+1 415 555 0100','500 Cloud Ave, San Francisco, USA','Hosting provider.' FROM DUAL WHERE @fresh;
SET @cp1 := (SELECT id FROM counterparties WHERE name='Orbit Software LLP' AND owner_id=@a);
SET @cp2 := (SELECT id FROM counterparties WHERE name='Acme Retail Pvt Ltd' AND owner_id=@a);
SET @cp3 := (SELECT id FROM counterparties WHERE name='BlueWave Logistics' AND owner_id=@a);
SET @cp4 := (SELECT id FROM counterparties WHERE name='Nimbus Cloud Services' AND owner_id=@a);

-- Contracts ----------------------------------------------------------------------------------
INSERT INTO contracts (owner_id,counterparty_id,reviewer_id,title,contract_type,effective_date,start_date,expiry_date,renewal_date,notice_period_days,contract_value,currency,status,tags,description,analysis_status,risk_score,risk_level)
  SELECT @a,@cp1,@v,'Orbit Managed Services Agreement','Service Agreement',CURDATE()-INTERVAL 340 DAY,CURDATE()-INTERVAL 340 DAY,CURDATE()+INTERVAL 25 DAY,CURDATE()+INTERVAL 25 DAY,30,600000.00,'INR','active','managed-services,renewal','Monthly managed IT services with automatic renewal.','completed',66,'high' FROM DUAL WHERE @fresh;
INSERT INTO contracts (owner_id,counterparty_id,reviewer_id,title,contract_type,effective_date,start_date,expiry_date,renewal_date,notice_period_days,contract_value,currency,status,tags,description,analysis_status,risk_score,risk_level)
  SELECT @a,@cp2,NULL,'Acme Master Supply Contract','Sales',CURDATE()-INTERVAL 225 DAY,CURDATE()-INTERVAL 225 DAY,CURDATE()+INTERVAL 140 DAY,NULL,45,1250000.00,'INR','active','supply,exclusive','Annual supply of retail goods with exclusivity.','completed',34,'medium' FROM DUAL WHERE @fresh;
INSERT INTO contracts (owner_id,counterparty_id,reviewer_id,title,contract_type,effective_date,start_date,expiry_date,notice_period_days,currency,status,tags,description)
  SELECT @a,@cp3,NULL,'BlueWave Freight NDA','NDA',CURDATE()-INTERVAL 60 DAY,CURDATE()-INTERVAL 60 DAY,CURDATE()+INTERVAL 400 DAY,NULL,'GBP','active','nda','Mutual confidentiality for freight pricing.' FROM DUAL WHERE @fresh;
INSERT INTO contracts (owner_id,counterparty_id,reviewer_id,title,contract_type,effective_date,start_date,expiry_date,contract_value,currency,status,tags,description)
  SELECT @a,@cp4,@v,'Nimbus Cloud Hosting Order','Vendor / Supplier',CURDATE()+INTERVAL 5 DAY,CURDATE()+INTERVAL 5 DAY,CURDATE()+INTERVAL 700 DAY,24000.00,'USD','under_review','cloud,hosting','Awaiting legal review before signature.' FROM DUAL WHERE @fresh;
INSERT INTO contracts (owner_id,counterparty_id,title,contract_type,effective_date,start_date,expiry_date,currency,status,tags,description)
  SELECT @a,@cp2,'Legacy Office Lease','Lease',CURDATE()-INTERVAL 750 DAY,CURDATE()-INTERVAL 750 DAY,CURDATE()-INTERVAL 20 DAY,'INR','expired','lease','Expired premises lease kept for reference.' FROM DUAL WHERE @fresh;
INSERT INTO contracts (owner_id,counterparty_id,title,contract_type,currency,status,description)
  SELECT @a,@cp1,'Draft Consulting Agreement','Consulting','INR','draft','Template draft; no dates yet.' FROM DUAL WHERE @fresh;
SET @c1 := (SELECT id FROM contracts WHERE title='Orbit Managed Services Agreement' AND owner_id=@a);
SET @c2 := (SELECT id FROM contracts WHERE title='Acme Master Supply Contract' AND owner_id=@a);
SET @c4 := (SELECT id FROM contracts WHERE title='Nimbus Cloud Hosting Order' AND owner_id=@a);

-- Searchable document text for the first two contracts (Copilot and text search work on these) ---
INSERT INTO document_chunks (contract_id,chunk_index,content,word_count)
  SELECT @c1,0,'SERVICE AGREEMENT between Orbit Software LLP and Acme Retail Pvt Ltd. The Client shall pay the Service Provider INR 50,000 per month, within thirty (30) days of receipt of each invoice. Late payments accrue interest at 2% per month on the outstanding amount. This Agreement shall automatically renew for successive twelve (12) month terms unless either party gives thirty (30) days written notice of non-renewal. Either party may terminate this Agreement for convenience on sixty (60) days written notice.',72 FROM DUAL WHERE @fresh;
INSERT INTO document_chunks (contract_id,chunk_index,content,word_count)
  SELECT @c1,1,'The Service Provider''s liability under this Agreement shall be unlimited, including for indirect and consequential damages. Each party shall keep the other party''s Confidential Information confidential for a period of five (5) years. This Agreement shall be governed by the laws of India and the courts at Mumbai shall have exclusive jurisdiction.',55 FROM DUAL WHERE @fresh;
INSERT INTO document_chunks (contract_id,chunk_index,content,word_count)
  SELECT @c2,0,'SUPPLY CONTRACT. During the term the Buyer shall purchase goods exclusively from the Supplier and shall not engage any other supplier of equivalent goods. The Buyer shall pay each invoice within forty-five (45) days. Either party may terminate this Contract on ninety (90) days written notice. Delivery dates are estimates and the Supplier is not liable for delays.',58 FROM DUAL WHERE @fresh;

-- AI analysis results (labelled as demo data) ---------------------------------------------------
INSERT INTO analysis_results (contract_id,mode,executive_summary,detailed_explanation,result_json,risk_score,model_name)
  SELECT @c1,'ai','A monthly managed-services agreement that renews automatically and leaves the provider with unlimited liability.','The Client pays INR 50,000 each month. The agreement renews every 12 months unless notice is given 30 days before the end of the term.\n\nThe provider is liable without any cap, which is unusual and worth negotiating.',
    '{"summary":{"executive_summary":"A monthly managed-services agreement that renews automatically and leaves the provider with unlimited liability.","parties":["Orbit Software LLP","Acme Retail Pvt Ltd"],"financial_terms":["INR 50,000 per month, payable within 30 days"],"termination_conditions":["60 days written notice for convenience"],"renewal_terms":["Auto-renews for 12 months unless 30 days notice"],"important_limitations":["Unlimited provider liability"]},"date_suggestions":[],"notes":{"demo":true}}',66,'demo-seed' FROM DUAL WHERE @fresh;
INSERT INTO analysis_results (contract_id,mode,executive_summary,result_json,risk_score,model_name)
  SELECT @c2,'ai','A one-year supply contract with buyer exclusivity and a long payment period.',
    '{"summary":{"executive_summary":"A one-year supply contract with buyer exclusivity and a long payment period.","parties":["Acme Retail Pvt Ltd","Supplier"]},"date_suggestions":[],"notes":{"demo":true}}',34,'demo-seed' FROM DUAL WHERE @fresh;

-- Clauses and risk findings (quotations match the text above, so they show as verified) ---------
INSERT INTO clauses (contract_id,clause_type,summary,source_quote,quote_verified,chunk_index,confidence) SELECT @c1,'payment_terms','Invoices are payable within 30 days.','within thirty (30) days of receipt of each invoice',1,0,0.92 FROM DUAL WHERE @fresh;
INSERT INTO clauses (contract_id,clause_type,summary,source_quote,quote_verified,chunk_index,confidence) SELECT @c1,'penalties_late_fees','Late payments accrue 2% interest per month.','Late payments accrue interest at 2% per month',1,0,0.9 FROM DUAL WHERE @fresh;
INSERT INTO clauses (contract_id,clause_type,summary,source_quote,quote_verified,chunk_index,confidence) SELECT @c1,'automatic_renewal','Renews for 12 months unless 30 days notice is given.','shall automatically renew for successive twelve (12) month terms',1,0,0.95 FROM DUAL WHERE @fresh;
INSERT INTO clauses (contract_id,clause_type,summary,source_quote,quote_verified,chunk_index,confidence) SELECT @c1,'termination','Either party may terminate on 60 days notice.','Either party may terminate this Agreement for convenience on sixty (60) days written notice',1,0,0.9 FROM DUAL WHERE @fresh;
INSERT INTO clauses (contract_id,clause_type,summary,source_quote,quote_verified,chunk_index,confidence) SELECT @c1,'limitation_of_liability','Provider liability is unlimited.','liability under this Agreement shall be unlimited',1,1,0.9 FROM DUAL WHERE @fresh;
INSERT INTO clauses (contract_id,clause_type,summary,source_quote,quote_verified,chunk_index,confidence) SELECT @c1,'confidentiality','Confidentiality lasts five years.','keep the other party''s Confidential Information confidential for a period of five (5) years',1,1,0.9 FROM DUAL WHERE @fresh;
INSERT INTO clauses (contract_id,clause_type,summary,source_quote,quote_verified,chunk_index,confidence) SELECT @c1,'governing_law','Indian law; Mumbai courts.','governed by the laws of India',1,1,0.9 FROM DUAL WHERE @fresh;
INSERT INTO clauses (contract_id,clause_type,summary,source_quote,quote_verified,chunk_index,confidence) SELECT @c2,'non_compete_exclusivity','The buyer must buy exclusively from the supplier.','shall purchase goods exclusively from the Supplier',1,0,0.9 FROM DUAL WHERE @fresh;

INSERT INTO risk_findings (contract_id,clause_id,title,description,severity,reason,source_quote,quote_verified,is_inferred,suggested_action,confidence)
  SELECT @c1,(SELECT id FROM clauses WHERE contract_id=@c1 AND clause_type='automatic_renewal' LIMIT 1),'Automatic renewal with a short notice window','The contract renews itself for 12 months unless notice is given 30 days before the end.','high','A missed notice date locks you in for another year.','shall automatically renew for successive twelve (12) month terms',1,0,'Diarise the notice deadline and decide on renewal early.',0.9 FROM DUAL WHERE @fresh;
INSERT INTO risk_findings (contract_id,clause_id,title,description,severity,reason,source_quote,quote_verified,is_inferred,suggested_action,confidence)
  SELECT @c1,(SELECT id FROM clauses WHERE contract_id=@c1 AND clause_type='limitation_of_liability' LIMIT 1),'Unlimited provider liability','There is no cap on the provider''s liability, including indirect damages.','high','Unlimited exposure is unusual and may make the provider push back or raise prices.','liability under this Agreement shall be unlimited',1,0,'Consider a liability cap linked to annual fees.',0.9 FROM DUAL WHERE @fresh;
INSERT INTO risk_findings (contract_id,clause_id,title,description,severity,reason,source_quote,quote_verified,is_inferred,suggested_action,confidence)
  SELECT @c1,(SELECT id FROM clauses WHERE contract_id=@c1 AND clause_type='penalties_late_fees' LIMIT 1),'High late-payment interest','2% per month is about 27% a year.','medium','Above typical commercial rates.','Late payments accrue interest at 2% per month',1,0,'Negotiate a lower rate or a grace period.',0.8 FROM DUAL WHERE @fresh;
INSERT INTO risk_findings (contract_id,clause_id,title,description,severity,reason,source_quote,quote_verified,is_inferred,suggested_action,confidence)
  SELECT @c1,NULL,'No data-protection clause','Nothing in the text covers how personal data is handled.','medium','Expected in a managed-services agreement.',NULL,0,1,'Add a data-processing clause or addendum.',0.6 FROM DUAL WHERE @fresh;
INSERT INTO risk_findings (contract_id,clause_id,title,description,severity,reason,source_quote,quote_verified,is_inferred,suggested_action,confidence)
  SELECT @c2,(SELECT id FROM clauses WHERE contract_id=@c2 AND clause_type='non_compete_exclusivity' LIMIT 1),'Buyer exclusivity','The buyer cannot use any other supplier of equivalent goods.','medium','Limits flexibility if the supplier under-delivers.','shall purchase goods exclusively from the Supplier',1,0,'Ask for a right to buy elsewhere if delivery targets are missed.',0.85 FROM DUAL WHERE @fresh;
INSERT INTO risk_findings (contract_id,clause_id,title,description,severity,reason,source_quote,quote_verified,is_inferred,suggested_action,confidence)
  SELECT @c2,NULL,'No service-level or delay remedy','The supplier is not liable for delays and no remedy is described.','high','Delivery risk sits entirely with the buyer.',NULL,0,1,'Add delivery commitments and a remedy for late delivery.',0.6 FROM DUAL WHERE @fresh;

-- Suggestions awaiting human review (try the approve / edit / reject workflow) -------------------
INSERT INTO obligation_suggestions (contract_id,kind,title,description,responsible_party,recurrence,source_quote,quote_verified,confidence)
  SELECT @c1,'obligation','Decide on renewal and send non-renewal notice if needed','Notice must be given 30 days before the term ends.','Client','none','unless either party gives thirty (30) days written notice of non-renewal',1,0.85 FROM DUAL WHERE @fresh;
INSERT INTO obligation_suggestions (contract_id,kind,title,description,amount,currency,source_quote,quote_verified,confidence)
  SELECT @c1,'payment','Monthly service fee','Payable within 30 days of invoice.',50000.00,'INR','The Client shall pay the Service Provider INR 50,000 per month',1,0.9 FROM DUAL WHERE @fresh;

-- Obligations ------------------------------------------------------------------------------------
INSERT INTO obligations (contract_id,title,description,responsible_party,owner_id,due_date,recurrence,priority,status) SELECT @c1,'Submit monthly service report','Provider sends the monthly performance report.','Service Provider',@a,CURDATE()+INTERVAL 6 DAY,'monthly','high','open' FROM DUAL WHERE @fresh;
INSERT INTO obligations (contract_id,title,description,responsible_party,owner_id,due_date,priority,status) SELECT @c1,'Quarterly SLA review meeting','Review uptime and ticket metrics.','Both parties',@v,CURDATE()-INTERVAL 4 DAY,'medium','open' FROM DUAL WHERE @fresh;
INSERT INTO obligations (contract_id,title,description,responsible_party,owner_id,due_date,priority,status) SELECT @c2,'Provide annual purchase forecast','Buyer shares a 12-month forecast.','Buyer',@a,CURDATE()+INTERVAL 30 DAY,'low','open' FROM DUAL WHERE @fresh;
INSERT INTO obligations (contract_id,title,description,responsible_party,owner_id,due_date,priority,status,completion_notes) SELECT @c2,'Renew supplier insurance certificate','Collect updated certificate.','Supplier',@a,CURDATE()-INTERVAL 15 DAY,'medium','completed','Certificate received and filed.' FROM DUAL WHERE @fresh;
INSERT INTO obligations (contract_id,title,description,responsible_party,due_date,priority,status,completion_notes) SELECT @c4,'Security questionnaire from Nimbus','Awaiting vendor answers before signature.','Vendor',CURDATE()+INTERVAL 3 DAY,'high','open',NULL FROM DUAL WHERE @fresh;

-- Payments (INR and USD are never added together in totals) --------------------------------------
INSERT INTO payments (contract_id,amount,currency,category,due_date,status,reference_number) SELECT @c1,50000.00,'INR','Monthly fee',CURDATE()+INTERVAL 5 DAY,'planned',NULL FROM DUAL WHERE @fresh;
INSERT INTO payments (contract_id,amount,currency,category,due_date,status,reference_number) SELECT @c1,50000.00,'INR','Monthly fee',CURDATE()-INTERVAL 25 DAY,'paid','UTR-DEMO-0001' FROM DUAL WHERE @fresh;
UPDATE payments SET paid_date=due_date WHERE contract_id=@c1 AND status='paid' AND paid_date IS NULL AND reference_number='UTR-DEMO-0001' AND @fresh;
INSERT INTO payments (contract_id,amount,currency,category,due_date,status) SELECT @c1,50000.00,'INR','Monthly fee',CURDATE()-INTERVAL 6 DAY,'planned' FROM DUAL WHERE @fresh;
INSERT INTO payments (contract_id,amount,currency,category,due_date,status) SELECT @c2,312500.00,'INR','Quarterly installment',CURDATE()+INTERVAL 20 DAY,'planned' FROM DUAL WHERE @fresh;
INSERT INTO payments (contract_id,amount,currency,category,due_date,status) SELECT @c4,2000.00,'USD','Setup fee',CURDATE()+INTERVAL 12 DAY,'planned' FROM DUAL WHERE @fresh;

-- Review notes, notifications, activity ---------------------------------------------------------
INSERT INTO review_notes (contract_id,user_id,note) SELECT @c1,@a,'Renewal notice window is already open - confirm with the vendor manager this week.' FROM DUAL WHERE @fresh;
INSERT INTO notifications (user_id,type,title,message,link,is_read) SELECT @a,'contract_expiry','Contract expiring in 25 days','"Orbit Managed Services Agreement" is about to expire.',CONCAT('index.php?page=contract_view&id=',@c1),0 FROM DUAL WHERE @fresh;
INSERT INTO notifications (user_id,type,title,message,link,is_read) SELECT @a,'obligation_overdue','Obligation overdue','"Quarterly SLA review meeting" is overdue.','index.php?page=obligations&status=overdue',0 FROM DUAL WHERE @fresh;
INSERT INTO notifications (user_id,type,title,message,link,is_read) SELECT @a,'analysis_completed','AI analysis complete','"Orbit Managed Services Agreement": 4 finding(s), 2 suggestion(s) to review.',CONCAT('index.php?page=contract_view&id=',@c1,'#analysis'),0 FROM DUAL WHERE @fresh;
INSERT INTO notifications (user_id,type,title,message,link,is_read) SELECT @v,'review_request','Review requested','You were assigned to review "Orbit Managed Services Agreement".',CONCAT('index.php?page=contract_view&id=',@c1),0 FROM DUAL WHERE @fresh;
INSERT INTO activity_logs (user_id,action,entity_type,entity_id,metadata) SELECT @a,'contract.create','contract',@c1,'{"title":"Orbit Managed Services Agreement"}' FROM DUAL WHERE @fresh;
INSERT INTO activity_logs (user_id,action,entity_type,entity_id,metadata) SELECT @a,'analysis.completed','contract',@c1,'{"mode":"ai","findings":4,"suggestions":2,"demo":true}' FROM DUAL WHERE @fresh;
INSERT INTO activity_logs (user_id,action,entity_type,entity_id,metadata) SELECT @a,'contract.create','contract',@c2,'{"title":"Acme Master Supply Contract"}' FROM DUAL WHERE @fresh;
INSERT INTO activity_logs (user_id,action,entity_type,entity_id,metadata) SELECT @a,'obligation.status','obligation',(SELECT id FROM obligations WHERE title='Renew supplier insurance certificate' LIMIT 1),'{"from":"open","to":"completed"}' FROM DUAL WHERE @fresh;
INSERT INTO activity_logs (user_id,action,entity_type,entity_id,metadata) SELECT @a,'payment.status','payment',(SELECT id FROM payments WHERE reference_number='UTR-DEMO-0001' LIMIT 1),'{"from":"planned","to":"paid"}' FROM DUAL WHERE @fresh;

-- Starter clause templates (reference material, not guaranteed legally suitable) -----------------
INSERT INTO clause_templates (name,category,clause_text,plain_explanation,tags,notes,created_by) SELECT 'Mutual confidentiality (5 years)','confidentiality','Each party shall keep the other party''s Confidential Information confidential and shall not disclose it to any third party for a period of five (5) years from disclosure, except as required by law.','Both sides keep each other''s secrets for five years.','nda,mutual','Check the carve-outs with a lawyer.',@a FROM DUAL WHERE @fresh;
INSERT INTO clause_templates (name,category,clause_text,plain_explanation,tags,notes,created_by) SELECT 'Termination for convenience','termination','Either party may terminate this Agreement for any reason on thirty (30) days prior written notice to the other party.','Either side can leave with a month''s notice.','exit,notice',NULL,@a FROM DUAL WHERE @fresh;
INSERT INTO clause_templates (name,category,clause_text,plain_explanation,tags,notes,created_by) SELECT 'Liability cap','limitation_of_liability','Each party''s total aggregate liability under this Agreement shall not exceed the fees paid or payable in the twelve (12) months preceding the claim.','Caps what either side can be asked to pay at about a year of fees.','liability,cap','Usually excludes fraud and wilful misconduct.',@a FROM DUAL WHERE @fresh;
INSERT INTO clause_templates (name,category,clause_text,plain_explanation,tags,notes,created_by) SELECT 'Payment within 30 days','payment_terms','The Client shall pay each undisputed invoice within thirty (30) days of receipt.','Invoices are due a month after they arrive.','payment',NULL,@a FROM DUAL WHERE @fresh;
INSERT INTO clause_templates (name,category,clause_text,plain_explanation,tags,notes,created_by) SELECT 'Governing law and courts','governing_law','This Agreement is governed by the laws of India. The courts at Mumbai shall have exclusive jurisdiction over any dispute arising from it.','Which country''s law applies and where disputes are heard.','law,jurisdiction','Change the place to suit your situation.',@a FROM DUAL WHERE @fresh;

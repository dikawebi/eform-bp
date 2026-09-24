import Create from './Create';
export default function Edit({ settlement, categories }) { return <Create settlement={settlement} sources={[{ source_type: settlement.source_type, source_id: settlement.source_id, number: settlement.settlement_number, advance: settlement.advance_amount }]} categories={categories} />; }

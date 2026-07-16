export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  public: {
    Tables: {
      app_config: {
        Row: {
          created_at: string
          description: string | null
          effective_from: string
          is_active: boolean
          key: string
          unit: string | null
          updated_at: string
          value_date: string | null
          value_num: number | null
          value_text: string | null
        }
        Insert: {
          created_at?: string
          description?: string | null
          effective_from: string
          is_active?: boolean
          key: string
          unit?: string | null
          updated_at?: string
          value_date?: string | null
          value_num?: number | null
          value_text?: string | null
        }
        Update: {
          created_at?: string
          description?: string | null
          effective_from?: string
          is_active?: boolean
          key?: string
          unit?: string | null
          updated_at?: string
          value_date?: string | null
          value_num?: number | null
          value_text?: string | null
        }
        Relationships: []
      }
      auto_consumable_rules: {
        Row: {
          condition: string
          consumable_id: string
          created_at: string
          id: string
          is_active: boolean
          note: string | null
          qty: number
          updated_at: string
        }
        Insert: {
          condition: string
          consumable_id: string
          created_at?: string
          id?: string
          is_active?: boolean
          note?: string | null
          qty: number
          updated_at?: string
        }
        Update: {
          condition?: string
          consumable_id?: string
          created_at?: string
          id?: string
          is_active?: boolean
          note?: string | null
          qty?: number
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "auto_consumable_rules_consumable_id_fkey"
            columns: ["consumable_id"]
            isOneToOne: false
            referencedRelation: "consumables"
            referencedColumns: ["id"]
          },
        ]
      }
      consumable_consumptions: {
        Row: {
          consumable_id: string
          consumed_at: string
          created_at: string
          id: string
          qty: number
          reason: string | null
          sale_id: string | null
          source: string
          updated_at: string
        }
        Insert: {
          consumable_id: string
          consumed_at: string
          created_at?: string
          id?: string
          qty: number
          reason?: string | null
          sale_id?: string | null
          source: string
          updated_at?: string
        }
        Update: {
          consumable_id?: string
          consumed_at?: string
          created_at?: string
          id?: string
          qty?: number
          reason?: string | null
          sale_id?: string | null
          source?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "consumable_consumptions_consumable_id_fkey"
            columns: ["consumable_id"]
            isOneToOne: false
            referencedRelation: "consumables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "consumable_consumptions_sale_id_fkey"
            columns: ["sale_id"]
            isOneToOne: false
            referencedRelation: "sales"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "consumable_consumptions_sale_id_fkey"
            columns: ["sale_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["sale_id"]
          },
        ]
      }
      consumables: {
        Row: {
          activation_date: string | null
          archived_at: string | null
          category: string
          created_at: string
          id: string
          in_transit_via_expenses: number
          initial_in_transit: number
          initial_stock: number
          is_active: boolean
          is_packaging: boolean
          name: string
          received_via_expenses: number
          stock_remaining: number | null
          unit_cost: number
          updated_at: string
          used_in_sales: number
        }
        Insert: {
          activation_date?: string | null
          archived_at?: string | null
          category: string
          created_at?: string
          id?: string
          in_transit_via_expenses?: number
          initial_in_transit?: number
          initial_stock?: number
          is_active?: boolean
          is_packaging?: boolean
          name: string
          received_via_expenses?: number
          stock_remaining?: number | null
          unit_cost?: number
          updated_at?: string
          used_in_sales?: number
        }
        Update: {
          activation_date?: string | null
          archived_at?: string | null
          category?: string
          created_at?: string
          id?: string
          in_transit_via_expenses?: number
          initial_in_transit?: number
          initial_stock?: number
          is_active?: boolean
          is_packaging?: boolean
          name?: string
          received_via_expenses?: number
          stock_remaining?: number | null
          unit_cost?: number
          updated_at?: string
          used_in_sales?: number
        }
        Relationships: []
      }
      currency_rates: {
        Row: {
          as_of: string
          created_at: string
          currency: string
          id: string
          note: string | null
          rate_to_uah: number
          source: string
          updated_at: string
        }
        Insert: {
          as_of: string
          created_at?: string
          currency: string
          id?: string
          note?: string | null
          rate_to_uah: number
          source: string
          updated_at?: string
        }
        Update: {
          as_of?: string
          created_at?: string
          currency?: string
          id?: string
          note?: string | null
          rate_to_uah?: number
          source?: string
          updated_at?: string
        }
        Relationships: []
      }
      expenses: {
        Row: {
          amount: number
          amount_currency: string
          amount_rate: number
          amount_uah: number
          category: string
          consumable_id: string | null
          consumable_qty: number | null
          created_at: string
          description: string
          id: string
          linked_sale_id: string | null
          note: string | null
          spent_at: string
          treatment: string
          updated_at: string
        }
        Insert: {
          amount: number
          amount_currency: string
          amount_rate: number
          amount_uah: number
          category: string
          consumable_id?: string | null
          consumable_qty?: number | null
          created_at?: string
          description: string
          id?: string
          linked_sale_id?: string | null
          note?: string | null
          spent_at: string
          treatment: string
          updated_at?: string
        }
        Update: {
          amount?: number
          amount_currency?: string
          amount_rate?: number
          amount_uah?: number
          category?: string
          consumable_id?: string | null
          consumable_qty?: number | null
          created_at?: string
          description?: string
          id?: string
          linked_sale_id?: string | null
          note?: string | null
          spent_at?: string
          treatment?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "expenses_consumable_id_fkey"
            columns: ["consumable_id"]
            isOneToOne: false
            referencedRelation: "consumables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "expenses_linked_sale_id_fkey"
            columns: ["linked_sale_id"]
            isOneToOne: false
            referencedRelation: "sales"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "expenses_linked_sale_id_fkey"
            columns: ["linked_sale_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["sale_id"]
          },
        ]
      }
      games: {
        Row: {
          code: string
          created_at: string
          is_active: boolean
          name: string
          updated_at: string
        }
        Insert: {
          code: string
          created_at?: string
          is_active?: boolean
          name: string
          updated_at?: string
        }
        Update: {
          code?: string
          created_at?: string
          is_active?: boolean
          name?: string
          updated_at?: string
        }
        Relationships: []
      }
      inventory_adjustment_items: {
        Row: {
          adjustment_id: string
          cost_audit: string
          created_at: string
          id: string
          mgmt_unit: number
          product_id: string
          prro_unit: number
          qty_delta: number
          updated_at: string
        }
        Insert: {
          adjustment_id: string
          cost_audit: string
          created_at?: string
          id?: string
          mgmt_unit: number
          product_id: string
          prro_unit: number
          qty_delta: number
          updated_at?: string
        }
        Update: {
          adjustment_id?: string
          cost_audit?: string
          created_at?: string
          id?: string
          mgmt_unit?: number
          product_id?: string
          prro_unit?: number
          qty_delta?: number
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "inventory_adjustment_items_adjustment_id_fkey"
            columns: ["adjustment_id"]
            isOneToOne: false
            referencedRelation: "inventory_adjustments"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
        ]
      }
      inventory_adjustments: {
        Row: {
          adjustment_date: string
          adjustment_kind: string
          adjustment_no: string
          created_at: string
          id: string
          note: string | null
          source_ref: string | null
          updated_at: string
        }
        Insert: {
          adjustment_date: string
          adjustment_kind: string
          adjustment_no: string
          created_at?: string
          id?: string
          note?: string | null
          source_ref?: string | null
          updated_at?: string
        }
        Update: {
          adjustment_date?: string
          adjustment_kind?: string
          adjustment_no?: string
          created_at?: string
          id?: string
          note?: string | null
          source_ref?: string | null
          updated_at?: string
        }
        Relationships: []
      }
      inventory_reservations: {
        Row: {
          committed_at: string | null
          created_at: string
          fulfillment_id: string
          id: string
          product_id: string
          qty: number
          released_at: string | null
          state: string
        }
        Insert: {
          committed_at?: string | null
          created_at?: string
          fulfillment_id: string
          id?: string
          product_id: string
          qty: number
          released_at?: string | null
          state?: string
        }
        Update: {
          committed_at?: string | null
          created_at?: string
          fulfillment_id?: string
          id?: string
          product_id?: string
          qty?: number
          released_at?: string | null
          state?: string
        }
        Relationships: [
          {
            foreignKeyName: "inventory_reservations_fulfillment_id_fkey"
            columns: ["fulfillment_id"]
            isOneToOne: false
            referencedRelation: "mystery_fulfillments"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "inventory_reservations_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "inventory_reservations_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_reservations_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_reservations_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_reservations_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_reservations_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "inventory_reservations_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_reservations_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_reservations_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
        ]
      }
      mystery_box_types: {
        Row: {
          created_at: string
          expected_pack_count: number
          has_holo: boolean
          holo_cost: number
          id: string
          product_id: string
          provisional_unit_cost: number
          updated_at: string
        }
        Insert: {
          created_at?: string
          expected_pack_count: number
          has_holo?: boolean
          holo_cost?: number
          id?: string
          product_id: string
          provisional_unit_cost: number
          updated_at?: string
        }
        Update: {
          created_at?: string
          expected_pack_count?: number
          has_holo?: boolean
          holo_cost?: number
          id?: string
          product_id?: string
          provisional_unit_cost?: number
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: true
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: true
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: true
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: true
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: true
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: true
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: true
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: true
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: true
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
        ]
      }
      mystery_contents: {
        Row: {
          component_product_id: string
          cost_snapshot_at: string | null
          created_at: string
          id: string
          mgmt_unit_snapshot: number | null
          prro_unit_snapshot: number | null
          qty: number
          sale_item_id: string
          source: string
          updated_at: string
          writeoff_item_id: string | null
        }
        Insert: {
          component_product_id: string
          cost_snapshot_at?: string | null
          created_at?: string
          id?: string
          mgmt_unit_snapshot?: number | null
          prro_unit_snapshot?: number | null
          qty: number
          sale_item_id: string
          source: string
          updated_at?: string
          writeoff_item_id?: string | null
        }
        Update: {
          component_product_id?: string
          cost_snapshot_at?: string | null
          created_at?: string
          id?: string
          mgmt_unit_snapshot?: number | null
          prro_unit_snapshot?: number | null
          qty?: number
          sale_item_id?: string
          source?: string
          updated_at?: string
          writeoff_item_id?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "mystery_contents_component_product_id_fkey"
            columns: ["component_product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "mystery_contents_component_product_id_fkey"
            columns: ["component_product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_contents_component_product_id_fkey"
            columns: ["component_product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_contents_component_product_id_fkey"
            columns: ["component_product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_contents_component_product_id_fkey"
            columns: ["component_product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_contents_component_product_id_fkey"
            columns: ["component_product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "mystery_contents_component_product_id_fkey"
            columns: ["component_product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_contents_component_product_id_fkey"
            columns: ["component_product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_contents_component_product_id_fkey"
            columns: ["component_product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_contents_sale_item_id_fkey"
            columns: ["sale_item_id"]
            isOneToOne: false
            referencedRelation: "sale_items"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "mystery_contents_sale_item_id_fkey"
            columns: ["sale_item_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["sale_item_id"]
          },
          {
            foreignKeyName: "mystery_contents_sale_item_id_fkey"
            columns: ["sale_item_id"]
            isOneToOne: false
            referencedRelation: "v_sale_item_financials"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "mystery_contents_writeoff_item_id_fkey"
            columns: ["writeoff_item_id"]
            isOneToOne: true
            referencedRelation: "writeoff_items"
            referencedColumns: ["id"]
          },
        ]
      }
      mystery_fulfillment_items: {
        Row: {
          created_at: string
          fulfillment_id: string
          id: string
          product_id: string
          qty: number
          reservation_id: string
        }
        Insert: {
          created_at?: string
          fulfillment_id: string
          id?: string
          product_id: string
          qty: number
          reservation_id: string
        }
        Update: {
          created_at?: string
          fulfillment_id?: string
          id?: string
          product_id?: string
          qty?: number
          reservation_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "mystery_fulfillment_items_fulfillment_id_fkey"
            columns: ["fulfillment_id"]
            isOneToOne: false
            referencedRelation: "mystery_fulfillments"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "mystery_fulfillment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "mystery_fulfillment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_fulfillment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_fulfillment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_fulfillment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_fulfillment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "mystery_fulfillment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_fulfillment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_fulfillment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_fulfillment_items_reservation_id_fkey"
            columns: ["reservation_id"]
            isOneToOne: true
            referencedRelation: "inventory_reservations"
            referencedColumns: ["id"]
          },
        ]
      }
      mystery_fulfillments: {
        Row: {
          committed_at: string | null
          created_at: string
          created_by: string | null
          id: string
          note: string | null
          released_at: string | null
          reserved_at: string | null
          reversed_at: string | null
          sale_item_id: string
          state: string
          updated_at: string
        }
        Insert: {
          committed_at?: string | null
          created_at?: string
          created_by?: string | null
          id?: string
          note?: string | null
          released_at?: string | null
          reserved_at?: string | null
          reversed_at?: string | null
          sale_item_id: string
          state?: string
          updated_at?: string
        }
        Update: {
          committed_at?: string | null
          created_at?: string
          created_by?: string | null
          id?: string
          note?: string | null
          released_at?: string | null
          reserved_at?: string | null
          reversed_at?: string | null
          sale_item_id?: string
          state?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "mystery_fulfillments_sale_item_id_fkey"
            columns: ["sale_item_id"]
            isOneToOne: true
            referencedRelation: "sale_items"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "mystery_fulfillments_sale_item_id_fkey"
            columns: ["sale_item_id"]
            isOneToOne: true
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["sale_item_id"]
          },
          {
            foreignKeyName: "mystery_fulfillments_sale_item_id_fkey"
            columns: ["sale_item_id"]
            isOneToOne: true
            referencedRelation: "v_sale_item_financials"
            referencedColumns: ["id"]
          },
        ]
      }
      mystery_return_components: {
        Row: {
          created_at: string
          id: string
          mgmt_unit: number
          mystery_content_id: string
          product_id: string
          prro_unit: number
          qty: number
          refund_item_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          mgmt_unit: number
          mystery_content_id: string
          product_id: string
          prro_unit: number
          qty: number
          refund_item_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          mgmt_unit?: number
          mystery_content_id?: string
          product_id?: string
          prro_unit?: number
          qty?: number
          refund_item_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "mystery_return_components_mystery_content_id_fkey"
            columns: ["mystery_content_id"]
            isOneToOne: true
            referencedRelation: "mystery_contents"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "mystery_return_components_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "mystery_return_components_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_return_components_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_return_components_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_return_components_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_return_components_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "mystery_return_components_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_return_components_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_return_components_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_return_components_refund_item_id_fkey"
            columns: ["refund_item_id"]
            isOneToOne: false
            referencedRelation: "refund_items"
            referencedColumns: ["id"]
          },
        ]
      }
      order_statuses: {
        Row: {
          code: string
          created_at: string
          id: string
          is_active: boolean
          name_uk: string
          updated_at: string
        }
        Insert: {
          code: string
          created_at?: string
          id?: string
          is_active?: boolean
          name_uk: string
          updated_at?: string
        }
        Update: {
          code?: string
          created_at?: string
          id?: string
          is_active?: boolean
          name_uk?: string
          updated_at?: string
        }
        Relationships: []
      }
      payment_statuses: {
        Row: {
          code: string
          created_at: string
          id: string
          is_active: boolean
          name_uk: string
          updated_at: string
        }
        Insert: {
          code: string
          created_at?: string
          id?: string
          is_active?: boolean
          name_uk: string
          updated_at?: string
        }
        Update: {
          code?: string
          created_at?: string
          id?: string
          is_active?: boolean
          name_uk?: string
          updated_at?: string
        }
        Relationships: []
      }
      payment_types: {
        Row: {
          code: string
          created_at: string
          fee_fixed_config_key: string | null
          fee_min_config_key: string | null
          fee_pct_config_key: string | null
          id: string
          is_active: boolean
          name_uk: string
          updated_at: string
        }
        Insert: {
          code: string
          created_at?: string
          fee_fixed_config_key?: string | null
          fee_min_config_key?: string | null
          fee_pct_config_key?: string | null
          id?: string
          is_active?: boolean
          name_uk: string
          updated_at?: string
        }
        Update: {
          code?: string
          created_at?: string
          fee_fixed_config_key?: string | null
          fee_min_config_key?: string | null
          fee_pct_config_key?: string | null
          id?: string
          is_active?: boolean
          name_uk?: string
          updated_at?: string
        }
        Relationships: []
      }
      post_methods: {
        Row: {
          code: string
          created_at: string
          id: string
          is_active: boolean
          name_uk: string
          updated_at: string
        }
        Insert: {
          code: string
          created_at?: string
          id?: string
          is_active?: boolean
          name_uk: string
          updated_at?: string
        }
        Update: {
          code?: string
          created_at?: string
          id?: string
          is_active?: boolean
          name_uk?: string
          updated_at?: string
        }
        Relationships: []
      }
      product_brands: {
        Row: {
          code: string
          created_at: string
          is_active: boolean
          name: string
          updated_at: string
        }
        Insert: {
          code: string
          created_at?: string
          is_active?: boolean
          name: string
          updated_at?: string
        }
        Update: {
          code?: string
          created_at?: string
          is_active?: boolean
          name?: string
          updated_at?: string
        }
        Relationships: []
      }
      product_categories: {
        Row: {
          code: string
          created_at: string
          is_active: boolean
          name: string
          updated_at: string
        }
        Insert: {
          code: string
          created_at?: string
          is_active?: boolean
          name: string
          updated_at?: string
        }
        Update: {
          code?: string
          created_at?: string
          is_active?: boolean
          name?: string
          updated_at?: string
        }
        Relationships: []
      }
      product_languages: {
        Row: {
          code: string
          created_at: string
          is_active: boolean
          name: string
          updated_at: string
        }
        Insert: {
          code: string
          created_at?: string
          is_active?: boolean
          name: string
          updated_at?: string
        }
        Update: {
          code?: string
          created_at?: string
          is_active?: boolean
          name?: string
          updated_at?: string
        }
        Relationships: []
      }
      product_prices: {
        Row: {
          created_at: string
          effective_from: string
          id: string
          note: string | null
          price_kind: string
          product_id: string
          rrc: number
          source: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          effective_from: string
          id?: string
          note?: string | null
          price_kind?: string
          product_id: string
          rrc: number
          source: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          effective_from?: string
          id?: string
          note?: string | null
          price_kind?: string
          product_id?: string
          rrc?: number
          source?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
        ]
      }
      products: {
        Row: {
          archived_at: string | null
          brand_code: string | null
          category_code: string | null
          created_at: string
          full_name: string | null
          game_code: string | null
          gtin: string | null
          id: string
          is_active: boolean
          is_outlet: boolean
          is_sealed_pack: boolean
          language_code: string | null
          legacy_sku: string | null
          mystery_eligibility_override: string | null
          name: string | null
          sku: string
          updated_at: string
          weight_g: number | null
        }
        Insert: {
          archived_at?: string | null
          brand_code?: string | null
          category_code?: string | null
          created_at?: string
          full_name?: string | null
          game_code?: string | null
          gtin?: string | null
          id?: string
          is_active?: boolean
          is_outlet?: boolean
          is_sealed_pack?: boolean
          language_code?: string | null
          legacy_sku?: string | null
          mystery_eligibility_override?: string | null
          name?: string | null
          sku: string
          updated_at?: string
          weight_g?: number | null
        }
        Update: {
          archived_at?: string | null
          brand_code?: string | null
          category_code?: string | null
          created_at?: string
          full_name?: string | null
          game_code?: string | null
          gtin?: string | null
          id?: string
          is_active?: boolean
          is_outlet?: boolean
          is_sealed_pack?: boolean
          language_code?: string | null
          legacy_sku?: string | null
          mystery_eligibility_override?: string | null
          name?: string | null
          sku?: string
          updated_at?: string
          weight_g?: number | null
        }
        Relationships: [
          {
            foreignKeyName: "products_brand_code_fkey"
            columns: ["brand_code"]
            isOneToOne: false
            referencedRelation: "product_brands"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "products_category_code_fkey"
            columns: ["category_code"]
            isOneToOne: false
            referencedRelation: "product_categories"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "products_game_code_fkey"
            columns: ["game_code"]
            isOneToOne: false
            referencedRelation: "games"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "products_language_code_fkey"
            columns: ["language_code"]
            isOneToOne: false
            referencedRelation: "product_languages"
            referencedColumns: ["code"]
          },
        ]
      }
      purchase_lot_fee_allocations: {
        Row: {
          allocated_uah: number
          allocation_basis: number | null
          allocation_method: string
          created_at: string
          fee_type: string
          id: string
          purchase_lot_id: string
          updated_at: string
        }
        Insert: {
          allocated_uah: number
          allocation_basis?: number | null
          allocation_method: string
          created_at?: string
          fee_type: string
          id?: string
          purchase_lot_id: string
          updated_at?: string
        }
        Update: {
          allocated_uah?: number
          allocation_basis?: number | null
          allocation_method?: string
          created_at?: string
          fee_type?: string
          id?: string
          purchase_lot_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "purchase_lot_fee_allocations_purchase_lot_id_fkey"
            columns: ["purchase_lot_id"]
            isOneToOne: false
            referencedRelation: "purchase_lots"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "purchase_lot_fee_allocations_purchase_lot_id_fkey"
            columns: ["purchase_lot_id"]
            isOneToOne: false
            referencedRelation: "v_purchase_lot_costs"
            referencedColumns: ["id"]
          },
        ]
      }
      purchase_lot_statuses: {
        Row: {
          code: string
          created_at: string
          is_active: boolean
          is_stock: boolean
          name_uk: string
          updated_at: string
        }
        Insert: {
          code: string
          created_at?: string
          is_active?: boolean
          is_stock: boolean
          name_uk: string
          updated_at?: string
        }
        Update: {
          code?: string
          created_at?: string
          is_active?: boolean
          is_stock?: boolean
          name_uk?: string
          updated_at?: string
        }
        Relationships: []
      }
      purchase_lots: {
        Row: {
          created_at: string
          delivery_date: string | null
          forwarding_fee_uah: number
          goods_cost_uah: number
          id: string
          intl_shipping_uah: number
          legacy_status: string | null
          local_delivery_uah: number
          lot_code: string
          manual_unit_cost: number | null
          note: string | null
          product_id: string
          purchase_id: string
          qty: number
          status: string
          track_number: string | null
          updated_at: string
        }
        Insert: {
          created_at?: string
          delivery_date?: string | null
          forwarding_fee_uah: number
          goods_cost_uah: number
          id?: string
          intl_shipping_uah: number
          legacy_status?: string | null
          local_delivery_uah: number
          lot_code: string
          manual_unit_cost?: number | null
          note?: string | null
          product_id: string
          purchase_id: string
          qty: number
          status: string
          track_number?: string | null
          updated_at?: string
        }
        Update: {
          created_at?: string
          delivery_date?: string | null
          forwarding_fee_uah?: number
          goods_cost_uah?: number
          id?: string
          intl_shipping_uah?: number
          legacy_status?: string | null
          local_delivery_uah?: number
          lot_code?: string
          manual_unit_cost?: number | null
          note?: string | null
          product_id?: string
          purchase_id?: string
          qty?: number
          status?: string
          track_number?: string | null
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_purchase_id_fkey"
            columns: ["purchase_id"]
            isOneToOne: false
            referencedRelation: "purchases"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "purchase_lots_status_fkey"
            columns: ["status"]
            isOneToOne: false
            referencedRelation: "purchase_lot_statuses"
            referencedColumns: ["code"]
          },
        ]
      }
      purchases: {
        Row: {
          created_at: string
          created_by: string | null
          forwarding_fee_amount: number
          forwarding_fee_currency: string
          forwarding_fee_rate: number
          forwarding_fee_uah: number
          goods_total_amount: number
          goods_total_currency: string
          goods_total_rate: number
          goods_total_uah: number
          id: string
          intl_shipping_amount: number
          intl_shipping_currency: string
          intl_shipping_rate: number
          intl_shipping_uah: number
          local_delivery_amount: number
          local_delivery_currency: string
          local_delivery_rate: number
          local_delivery_uah: number
          note: string | null
          order_ref: string | null
          order_url: string
          ordered_at: string
          region_id: string
          supplier_name: string | null
          updated_at: string
        }
        Insert: {
          created_at?: string
          created_by?: string | null
          forwarding_fee_amount: number
          forwarding_fee_currency: string
          forwarding_fee_rate: number
          forwarding_fee_uah: number
          goods_total_amount: number
          goods_total_currency: string
          goods_total_rate: number
          goods_total_uah: number
          id?: string
          intl_shipping_amount: number
          intl_shipping_currency: string
          intl_shipping_rate: number
          intl_shipping_uah: number
          local_delivery_amount: number
          local_delivery_currency: string
          local_delivery_rate: number
          local_delivery_uah: number
          note?: string | null
          order_ref?: string | null
          order_url: string
          ordered_at: string
          region_id: string
          supplier_name?: string | null
          updated_at?: string
        }
        Update: {
          created_at?: string
          created_by?: string | null
          forwarding_fee_amount?: number
          forwarding_fee_currency?: string
          forwarding_fee_rate?: number
          forwarding_fee_uah?: number
          goods_total_amount?: number
          goods_total_currency?: string
          goods_total_rate?: number
          goods_total_uah?: number
          id?: string
          intl_shipping_amount?: number
          intl_shipping_currency?: string
          intl_shipping_rate?: number
          intl_shipping_uah?: number
          local_delivery_amount?: number
          local_delivery_currency?: string
          local_delivery_rate?: number
          local_delivery_uah?: number
          note?: string | null
          order_ref?: string | null
          order_url?: string
          ordered_at?: string
          region_id?: string
          supplier_name?: string | null
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "purchases_region_id_fkey"
            columns: ["region_id"]
            isOneToOne: false
            referencedRelation: "supplier_regions"
            referencedColumns: ["id"]
          },
        ]
      }
      refund_items: {
        Row: {
          condition: string
          created_at: string
          id: string
          mgmt_reversal_uah: number
          note: string | null
          prro_reversal_uah: number
          qty: number
          refund_id: string
          sale_item_id: string
          updated_at: string
        }
        Insert: {
          condition: string
          created_at?: string
          id?: string
          mgmt_reversal_uah?: number
          note?: string | null
          prro_reversal_uah?: number
          qty: number
          refund_id: string
          sale_item_id: string
          updated_at?: string
        }
        Update: {
          condition?: string
          created_at?: string
          id?: string
          mgmt_reversal_uah?: number
          note?: string | null
          prro_reversal_uah?: number
          qty?: number
          refund_id?: string
          sale_item_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "refund_items_refund_id_fkey"
            columns: ["refund_id"]
            isOneToOne: false
            referencedRelation: "refunds"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "refund_items_sale_item_id_fkey"
            columns: ["sale_item_id"]
            isOneToOne: false
            referencedRelation: "sale_items"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "refund_items_sale_item_id_fkey"
            columns: ["sale_item_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["sale_item_id"]
          },
          {
            foreignKeyName: "refund_items_sale_item_id_fkey"
            columns: ["sale_item_id"]
            isOneToOne: false
            referencedRelation: "v_sale_item_financials"
            referencedColumns: ["id"]
          },
        ]
      }
      refunds: {
        Row: {
          amount: number
          created_at: string
          id: string
          note: string | null
          reason: string | null
          refund_type: string
          refunded_at: string
          restock: boolean
          sale_id: string | null
          updated_at: string
        }
        Insert: {
          amount: number
          created_at?: string
          id?: string
          note?: string | null
          reason?: string | null
          refund_type: string
          refunded_at: string
          restock?: boolean
          sale_id?: string | null
          updated_at?: string
        }
        Update: {
          amount?: number
          created_at?: string
          id?: string
          note?: string | null
          reason?: string | null
          refund_type?: string
          refunded_at?: string
          restock?: boolean
          sale_id?: string | null
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "refunds_sale_id_fkey"
            columns: ["sale_id"]
            isOneToOne: false
            referencedRelation: "sales"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "refunds_sale_id_fkey"
            columns: ["sale_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["sale_id"]
          },
        ]
      }
      sale_channels: {
        Row: {
          code: string
          created_at: string
          id: string
          is_active: boolean
          is_online: boolean | null
          name_uk: string
          updated_at: string
        }
        Insert: {
          code: string
          created_at?: string
          id?: string
          is_active?: boolean
          is_online?: boolean | null
          name_uk: string
          updated_at?: string
        }
        Update: {
          code?: string
          created_at?: string
          id?: string
          is_active?: boolean
          is_online?: boolean | null
          name_uk?: string
          updated_at?: string
        }
        Relationships: []
      }
      sale_items: {
        Row: {
          cost_audit: string | null
          cost_fixed_at: string | null
          cost_method: string
          cost_state: string
          created_at: string
          discount_alloc: number
          id: string
          mgmt_unit: number | null
          note: string | null
          packaging_alloc: number
          payment_fee: number
          product_id: string
          prro_unit: number | null
          qty: number
          sale_id: string
          shop_delivery_alloc: number
          unit_price: number
          updated_at: string
        }
        Insert: {
          cost_audit?: string | null
          cost_fixed_at?: string | null
          cost_method?: string
          cost_state?: string
          created_at?: string
          discount_alloc?: number
          id?: string
          mgmt_unit?: number | null
          note?: string | null
          packaging_alloc?: number
          payment_fee?: number
          product_id: string
          prro_unit?: number | null
          qty: number
          sale_id: string
          shop_delivery_alloc?: number
          unit_price: number
          updated_at?: string
        }
        Update: {
          cost_audit?: string | null
          cost_fixed_at?: string | null
          cost_method?: string
          cost_state?: string
          created_at?: string
          discount_alloc?: number
          id?: string
          mgmt_unit?: number | null
          note?: string | null
          packaging_alloc?: number
          payment_fee?: number
          product_id?: string
          prro_unit?: number | null
          qty?: number
          sale_id?: string
          shop_delivery_alloc?: number
          unit_price?: number
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_sale_id_fkey"
            columns: ["sale_id"]
            isOneToOne: false
            referencedRelation: "sales"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "sale_items_sale_id_fkey"
            columns: ["sale_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["sale_id"]
          },
        ]
      }
      sales: {
        Row: {
          channel_id: string
          created_at: string
          created_by: string | null
          customer_name: string | null
          customer_phone: string | null
          discount_total: number
          id: string
          note: string | null
          opencart_order_id: string | null
          order_no: string
          order_status_id: string
          packaging_cost: number
          packaging_type_id: string | null
          payment_status_id: string
          payment_type_id: string
          post_method_id: string | null
          shop_delivery: number
          sold_at: string
          ttn: string | null
          updated_at: string
        }
        Insert: {
          channel_id: string
          created_at?: string
          created_by?: string | null
          customer_name?: string | null
          customer_phone?: string | null
          discount_total?: number
          id?: string
          note?: string | null
          opencart_order_id?: string | null
          order_no: string
          order_status_id: string
          packaging_cost?: number
          packaging_type_id?: string | null
          payment_status_id: string
          payment_type_id: string
          post_method_id?: string | null
          shop_delivery?: number
          sold_at: string
          ttn?: string | null
          updated_at?: string
        }
        Update: {
          channel_id?: string
          created_at?: string
          created_by?: string | null
          customer_name?: string | null
          customer_phone?: string | null
          discount_total?: number
          id?: string
          note?: string | null
          opencart_order_id?: string | null
          order_no?: string
          order_status_id?: string
          packaging_cost?: number
          packaging_type_id?: string | null
          payment_status_id?: string
          payment_type_id?: string
          post_method_id?: string | null
          shop_delivery?: number
          sold_at?: string
          ttn?: string | null
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "sales_channel_id_fkey"
            columns: ["channel_id"]
            isOneToOne: false
            referencedRelation: "sale_channels"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "sales_order_status_id_fkey"
            columns: ["order_status_id"]
            isOneToOne: false
            referencedRelation: "order_statuses"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "sales_packaging_type_fk"
            columns: ["packaging_type_id"]
            isOneToOne: false
            referencedRelation: "consumables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "sales_payment_status_id_fkey"
            columns: ["payment_status_id"]
            isOneToOne: false
            referencedRelation: "payment_statuses"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "sales_payment_type_id_fkey"
            columns: ["payment_type_id"]
            isOneToOne: false
            referencedRelation: "payment_types"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "sales_post_method_id_fkey"
            columns: ["post_method_id"]
            isOneToOne: false
            referencedRelation: "post_methods"
            referencedColumns: ["id"]
          },
        ]
      }
      staff: {
        Row: {
          created_at: string
          display_name: string | null
          id: string
          role: string
        }
        Insert: {
          created_at?: string
          display_name?: string | null
          id: string
          role: string
        }
        Update: {
          created_at?: string
          display_name?: string | null
          id?: string
          role?: string
        }
        Relationships: []
      }
      staff_permission_overrides: {
        Row: {
          created_at: string
          granted: boolean
          id: number
          permission_key: string
          staff_id: string
        }
        Insert: {
          created_at?: string
          granted?: boolean
          id?: never
          permission_key: string
          staff_id: string
        }
        Update: {
          created_at?: string
          granted?: boolean
          id?: never
          permission_key?: string
          staff_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "staff_permission_overrides_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
        ]
      }
      supplier_regions: {
        Row: {
          applicable_charges: string[]
          code: string
          created_at: string
          default_forwarding_currency: string
          default_goods_currency: string
          default_intl_shipping_currency: string
          default_local_currency: string
          has_intermediary: boolean
          id: string
          intermediary_name: string | null
          is_active: boolean
          name_uk: string
          updated_at: string
        }
        Insert: {
          applicable_charges?: string[]
          code: string
          created_at?: string
          default_forwarding_currency: string
          default_goods_currency: string
          default_intl_shipping_currency: string
          default_local_currency: string
          has_intermediary?: boolean
          id?: string
          intermediary_name?: string | null
          is_active?: boolean
          name_uk: string
          updated_at?: string
        }
        Update: {
          applicable_charges?: string[]
          code?: string
          created_at?: string
          default_forwarding_currency?: string
          default_goods_currency?: string
          default_intl_shipping_currency?: string
          default_local_currency?: string
          has_intermediary?: boolean
          id?: string
          intermediary_name?: string | null
          is_active?: boolean
          name_uk?: string
          updated_at?: string
        }
        Relationships: []
      }
      writeoff_items: {
        Row: {
          created_at: string
          id: string
          note: string | null
          product_id: string
          qty: number
          updated_at: string
          writeoff_id: string
        }
        Insert: {
          created_at?: string
          id?: string
          note?: string | null
          product_id: string
          qty: number
          updated_at?: string
          writeoff_id: string
        }
        Update: {
          created_at?: string
          id?: string
          note?: string | null
          product_id?: string
          qty?: number
          updated_at?: string
          writeoff_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "writeoff_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "writeoff_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "writeoff_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "writeoff_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "writeoff_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "writeoff_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "writeoff_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "writeoff_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "writeoff_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "writeoff_items_writeoff_id_fkey"
            columns: ["writeoff_id"]
            isOneToOne: false
            referencedRelation: "writeoffs"
            referencedColumns: ["id"]
          },
        ]
      }
      writeoffs: {
        Row: {
          created_at: string
          created_by: string | null
          expected_qty: number | null
          id: string
          mystery_fulfillment_id: string | null
          mystery_sale_id: string | null
          note: string | null
          reason: string | null
          type: string
          updated_at: string
          writeoff_no: string
          written_off_at: string
        }
        Insert: {
          created_at?: string
          created_by?: string | null
          expected_qty?: number | null
          id?: string
          mystery_fulfillment_id?: string | null
          mystery_sale_id?: string | null
          note?: string | null
          reason?: string | null
          type: string
          updated_at?: string
          writeoff_no: string
          written_off_at: string
        }
        Update: {
          created_at?: string
          created_by?: string | null
          expected_qty?: number | null
          id?: string
          mystery_fulfillment_id?: string | null
          mystery_sale_id?: string | null
          note?: string | null
          reason?: string | null
          type?: string
          updated_at?: string
          writeoff_no?: string
          written_off_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "writeoffs_mystery_fulfillment_id_fkey"
            columns: ["mystery_fulfillment_id"]
            isOneToOne: false
            referencedRelation: "mystery_fulfillments"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "writeoffs_mystery_sale_id_fkey"
            columns: ["mystery_sale_id"]
            isOneToOne: false
            referencedRelation: "sales"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "writeoffs_mystery_sale_id_fkey"
            columns: ["mystery_sale_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["sale_id"]
          },
        ]
      }
    }
    Views: {
      v_below_cost_alert: {
        Row: {
          gap_uah: number | null
          mgmt_cogs: number | null
          name: string | null
          order_no: string | null
          product_id: string | null
          qty: number | null
          revenue: number | null
          sale_id: string | null
          sale_item_id: string | null
          sku: string | null
          sold_at: string | null
        }
        Relationships: []
      }
      v_channel_report: {
        Row: {
          average_order_value: number | null
          channel_code: string | null
          channel_name: string | null
          contribution_margin: number | null
          contribution_margin_pct: number | null
          month: string | null
          orders: number | null
          revenue: number | null
          units: number | null
        }
        Relationships: []
      }
      v_cost_quality_exposure: {
        Row: {
          cost_state: string | null
          management_cogs: number | null
          month: string | null
          revenue: number | null
          sale_item_count: number | null
          units: number | null
        }
        Relationships: []
      }
      v_current_app_config: {
        Row: {
          description: string | null
          effective_from: string | null
          is_active: boolean | null
          key: string | null
          unit: string | null
          value_date: string | null
          value_num: number | null
          value_text: string | null
        }
        Relationships: []
      }
      v_current_rrc: {
        Row: {
          effective_from: string | null
          id: string | null
          note: string | null
          price_kind: string | null
          product_id: string | null
          rrc: number | null
          source: string | null
        }
        Relationships: [
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "product_prices_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
        ]
      }
      v_data_quality: {
        Row: {
          check_name: string | null
          details: string | null
          record_key: string | null
          severity: string | null
        }
        Relationships: []
      }
      v_forecast_margin: {
        Row: {
          available_qty: number | null
          expected_discount_amount: number | null
          expected_discount_pct: number | null
          forecast_margin: number | null
          forecast_net_revenue: number | null
          forecast_revenue_before_reserve: number | null
          management_inventory_cost: number | null
          manual_rrc: number | null
          name: string | null
          physical_qty: number | null
          product_id: string | null
          reserved_qty: number | null
          sku: string | null
        }
        Relationships: []
      }
      v_inventory_adjustment_pnl: {
        Row: {
          adjustment_date: string | null
          adjustment_kind: string | null
          is_operating_pnl: boolean | null
          mgmt_variance_uah: number | null
          product_id: string | null
          prro_variance_uah: number | null
          qty_delta: number | null
        }
        Relationships: [
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "inventory_adjustment_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
        ]
      }
      v_inventory_available: {
        Row: {
          available_qty: number | null
          name: string | null
          physical_qty: number | null
          product_id: string | null
          reserved_qty: number | null
          sku: string | null
        }
        Relationships: []
      }
      v_inventory_consumptions: {
        Row: {
          consumption_date: string | null
          product_id: string | null
          qty: number | null
          source_id: string | null
          source_kind: string | null
        }
        Relationships: []
      }
      v_inventory_cost_layers: {
        Row: {
          layer_code: string | null
          layer_date: string | null
          layer_scope: string | null
          mgmt_unit: number | null
          product_id: string | null
          prro_unit: number | null
          qty: number | null
          source_id: string | null
          source_kind: string | null
        }
        Relationships: []
      }
      v_inventory_dashboard_guardrails: {
        Row: {
          asset_mgmt_cost: number | null
          asset_prro_cost: number | null
          available_qty: number | null
          physical_qty: number | null
          reserved_qty: number | null
          warehouse_mgmt_cost: number | null
          warehouse_prro_cost: number | null
        }
        Relationships: []
      }
      v_inventory_fifo_valuation: {
        Row: {
          asset_mgmt_cost: number | null
          asset_prro_cost: number | null
          asset_qty: number | null
          inbound_qty: number | null
          name: string | null
          product_id: string | null
          sku: string | null
          warehouse_mgmt_cost: number | null
          warehouse_prro_cost: number | null
          warehouse_qty: number | null
        }
        Relationships: []
      }
      v_mystery_eligible_components: {
        Row: {
          available_qty: number | null
          component_name: string | null
          component_product_id: string | null
          component_sku: string | null
          mystery_product_id: string | null
          mystery_sku: string | null
          physical_qty: number | null
          reserved_qty: number | null
        }
        Relationships: [
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["mystery_product_id"]
            isOneToOne: true
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["mystery_product_id"]
            isOneToOne: true
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["mystery_product_id"]
            isOneToOne: true
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["mystery_product_id"]
            isOneToOne: true
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["mystery_product_id"]
            isOneToOne: true
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["mystery_product_id"]
            isOneToOne: true
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["mystery_product_id"]
            isOneToOne: true
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["mystery_product_id"]
            isOneToOne: true
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "mystery_box_types_product_id_fkey"
            columns: ["mystery_product_id"]
            isOneToOne: true
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
        ]
      }
      v_pnl_monthly: {
        Row: {
          cogs: number | null
          cogs_reversals: number | null
          contribution_margin: number | null
          direct_sale_costs: number | null
          inventory_adjustment_impact: number | null
          margin_pct: number | null
          month: string | null
          net_revenue: number | null
          operating_expenses: number | null
          prro_gross_profit: number | null
          refunds: number | null
          revenue: number | null
          true_net_profit: number | null
        }
        Relationships: []
      }
      v_purchase_lot_costs: {
        Row: {
          delivery_date: string | null
          id: string | null
          lot_code: string | null
          mgmt_total: number | null
          mgmt_unit: number | null
          product_id: string | null
          prro_total: number | null
          prro_unit: number | null
          purchase_id: string | null
          qty: number | null
          status: string | null
        }
        Relationships: [
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "purchase_lots_purchase_id_fkey"
            columns: ["purchase_id"]
            isOneToOne: false
            referencedRelation: "purchases"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "purchase_lots_status_fkey"
            columns: ["status"]
            isOneToOne: false
            referencedRelation: "purchase_lot_statuses"
            referencedColumns: ["code"]
          },
        ]
      }
      v_repeat_customers: {
        Row: {
          customer_name: string | null
          customer_phone: string | null
          first_order_at: string | null
          last_order_at: string | null
          lifetime_revenue: number | null
          order_count: number | null
        }
        Relationships: []
      }
      v_sale_item_financials: {
        Row: {
          cost_audit: string | null
          cost_fixed_at: string | null
          cost_method: string | null
          discount_alloc: number | null
          gross_profit: number | null
          id: string | null
          mgmt_cogs: number | null
          mgmt_unit: number | null
          net_profit: number | null
          packaging_alloc: number | null
          payment_fee: number | null
          product_id: string | null
          prro_cogs: number | null
          prro_unit: number | null
          qty: number | null
          revenue: number | null
          sale_id: string | null
          shop_delivery_alloc: number | null
          unit_price: number | null
        }
        Insert: {
          cost_audit?: string | null
          cost_fixed_at?: string | null
          cost_method?: string | null
          discount_alloc?: number | null
          gross_profit?: never
          id?: string | null
          mgmt_cogs?: never
          mgmt_unit?: number | null
          net_profit?: never
          packaging_alloc?: number | null
          payment_fee?: number | null
          product_id?: string | null
          prro_cogs?: never
          prro_unit?: number | null
          qty?: number | null
          revenue?: never
          sale_id?: string | null
          shop_delivery_alloc?: number | null
          unit_price?: number | null
        }
        Update: {
          cost_audit?: string | null
          cost_fixed_at?: string | null
          cost_method?: string | null
          discount_alloc?: number | null
          gross_profit?: never
          id?: string | null
          mgmt_cogs?: never
          mgmt_unit?: number | null
          net_profit?: never
          packaging_alloc?: number | null
          payment_fee?: number | null
          product_id?: string | null
          prro_cogs?: never
          prro_unit?: number | null
          qty?: number | null
          revenue?: never
          sale_id?: string | null
          shop_delivery_alloc?: number | null
          unit_price?: number | null
        }
        Relationships: [
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_forecast_margin"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_available"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_inventory_fifo_valuation"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_mystery_eligible_components"
            referencedColumns: ["component_product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_stock_alerts"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_top_skus"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "v_unpriced_inventory"
            referencedColumns: ["product_id"]
          },
          {
            foreignKeyName: "sale_items_sale_id_fkey"
            columns: ["sale_id"]
            isOneToOne: false
            referencedRelation: "sales"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "sale_items_sale_id_fkey"
            columns: ["sale_id"]
            isOneToOne: false
            referencedRelation: "v_below_cost_alert"
            referencedColumns: ["sale_id"]
          },
        ]
      }
      v_sales_report: {
        Row: {
          average_order_value: number | null
          date_from: string | null
          date_to: string | null
          margin_pct: number | null
          orders: number | null
          period_code: string | null
          period_name: string | null
          revenue: number | null
          true_net_profit: number | null
          units: number | null
        }
        Relationships: []
      }
      v_stock_alerts: {
        Row: {
          alert: string | null
          coverage_days: number | null
          name: string | null
          product_id: string | null
          sku: string | null
          sold_qty_30d: number | null
          stock_qty: number | null
        }
        Relationships: []
      }
      v_top_skus: {
        Row: {
          contribution_margin: number | null
          name: string | null
          product_id: string | null
          revenue: number | null
          sku: string | null
          units: number | null
        }
        Relationships: []
      }
      v_unpriced_inventory: {
        Row: {
          asset_mgmt_cost: number | null
          available_qty: number | null
          name: string | null
          physical_qty: number | null
          product_id: string | null
          reserved_qty: number | null
          sku: string | null
          warehouse_mgmt_cost: number | null
        }
        Relationships: []
      }
    }
    Functions: {
      fn_allocate_purchase_shared_fee: {
        Args: {
          p_allocation_method: string
          p_fee_type: string
          p_manual_allocations?: Json
          p_purchase_id: string
        }
        Returns: undefined
      }
      fn_assert_purchase_fee_allocation: {
        Args: { p_fee_type: string; p_purchase_id: string }
        Returns: undefined
      }
      fn_assert_refund_item_qty: {
        Args: { p_sale_item_id: string }
        Returns: undefined
      }
      fn_commit_mystery_fulfillment: {
        Args: { p_sale_item_id: string }
        Returns: {
          committed_at: string | null
          created_at: string
          created_by: string | null
          id: string
          note: string | null
          released_at: string | null
          reserved_at: string | null
          reversed_at: string | null
          sale_item_id: string
          state: string
          updated_at: string
        }
        SetofOptions: {
          from: "*"
          to: "mystery_fulfillments"
          isOneToOne: true
          isSetofReturn: false
        }
      }
      fn_fifo_cost_for_product: {
        Args: {
          p_exclude_sale_item_id?: string
          p_exclude_writeoff_item_id?: string
          p_product_id: string
          p_qty: number
          p_sale_date: string
        }
        Returns: {
          cost_audit: string
          cost_method: string
          mgmt_unit: number
          prro_unit: number
        }[]
      }
      fn_fix_sale_cogs: {
        Args: { p_sale_item_id: string }
        Returns: {
          cost_audit: string | null
          cost_fixed_at: string | null
          cost_method: string
          cost_state: string
          created_at: string
          discount_alloc: number
          id: string
          mgmt_unit: number | null
          note: string | null
          packaging_alloc: number
          payment_fee: number
          product_id: string
          prro_unit: number | null
          qty: number
          sale_id: string
          shop_delivery_alloc: number
          unit_price: number
          updated_at: string
        }
        SetofOptions: {
          from: "*"
          to: "sale_items"
          isOneToOne: true
          isSetofReturn: false
        }
      }
      fn_inventory_fifo_layers: {
        Args: { p_as_of?: string }
        Returns: {
          initial_qty: number
          layer_code: string
          layer_date: string
          mgmt_unit: number
          product_id: string
          prro_unit: number
          remaining_qty: number
          source_id: string
          source_kind: string
        }[]
      }
      fn_is_actual_sale: { Args: { p_sale_id: string }; Returns: boolean }
      fn_refresh_mystery_cogs: {
        Args: { p_sale_item_id: string }
        Returns: {
          cost_audit: string | null
          cost_fixed_at: string | null
          cost_method: string
          cost_state: string
          created_at: string
          discount_alloc: number
          id: string
          mgmt_unit: number | null
          note: string | null
          packaging_alloc: number
          payment_fee: number
          product_id: string
          prro_unit: number | null
          qty: number
          sale_id: string
          shop_delivery_alloc: number
          unit_price: number
          updated_at: string
        }
        SetofOptions: {
          from: "*"
          to: "sale_items"
          isOneToOne: true
          isSetofReturn: false
        }
      }
      fn_release_mystery_fulfillment: {
        Args: { p_sale_item_id: string }
        Returns: {
          committed_at: string | null
          created_at: string
          created_by: string | null
          id: string
          note: string | null
          released_at: string | null
          reserved_at: string | null
          reversed_at: string | null
          sale_item_id: string
          state: string
          updated_at: string
        }
        SetofOptions: {
          from: "*"
          to: "mystery_fulfillments"
          isOneToOne: true
          isSetofReturn: false
        }
      }
      fn_reserve_mystery_fulfillment: {
        Args: { p_components: Json; p_sale_item_id: string }
        Returns: {
          committed_at: string | null
          created_at: string
          created_by: string | null
          id: string
          note: string | null
          released_at: string | null
          reserved_at: string | null
          reversed_at: string | null
          sale_item_id: string
          state: string
          updated_at: string
        }
        SetofOptions: {
          from: "*"
          to: "mystery_fulfillments"
          isOneToOne: true
          isSetofReturn: false
        }
      }
      fn_reverse_mystery_fulfillment: {
        Args: { p_refund_id: string; p_sale_item_id: string }
        Returns: {
          condition: string
          created_at: string
          id: string
          mgmt_reversal_uah: number
          note: string | null
          prro_reversal_uah: number
          qty: number
          refund_id: string
          sale_item_id: string
          updated_at: string
        }
        SetofOptions: {
          from: "*"
          to: "refund_items"
          isOneToOne: true
          isSetofReturn: false
        }
      }
    }
    Enums: {
      [_ in never]: never
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
}

type DatabaseWithoutInternals = Omit<Database, "__InternalSupabase">

type DefaultSchema = DatabaseWithoutInternals[Extract<keyof Database, "public">]

export type Tables<
  DefaultSchemaTableNameOrOptions extends
    | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
        DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
      DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
      Row: infer R
    }
    ? R
    : never
  : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
        DefaultSchema["Views"])
    ? (DefaultSchema["Tables"] &
        DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
        Row: infer R
      }
      ? R
      : never
    : never

export type TablesInsert<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Insert: infer I
    }
    ? I
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Insert: infer I
      }
      ? I
      : never
    : never

export type TablesUpdate<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Update: infer U
    }
    ? U
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Update: infer U
      }
      ? U
      : never
    : never

export type Enums<
  DefaultSchemaEnumNameOrOptions extends
    | keyof DefaultSchema["Enums"]
    | { schema: keyof DatabaseWithoutInternals },
  EnumName extends DefaultSchemaEnumNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
    : never = never,
> = DefaultSchemaEnumNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
  : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
    ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
    : never

export type CompositeTypes<
  PublicCompositeTypeNameOrOptions extends
    | keyof DefaultSchema["CompositeTypes"]
    | { schema: keyof DatabaseWithoutInternals },
  CompositeTypeName extends PublicCompositeTypeNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
    : never = never,
> = PublicCompositeTypeNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
  : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
    ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
    : never

export const Constants = {
  public: {
    Enums: {},
  },
} as const

